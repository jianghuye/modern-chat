<?php
require_once 'db.php';

class Group {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * 创建群聊
     * @param int $creator_id 创建者ID
     * @param string $name 群聊名称
     * @param array $member_ids 初始成员ID数组
     * @return int|false 群聊ID或false
     */
    public function createGroup($creator_id, $name, $member_ids = []) {
        // 验证成员数量（包括创建者）不超过2000
        $total_members = count($member_ids) + 1; // +1 是创建者
        if ($total_members > 2000) {
            return false;
        }
        
        try {
            $this->conn->beginTransaction();
            
            // 创建群聊
            $stmt = $this->conn->prepare("INSERT INTO groups (name, creator_id, owner_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $creator_id, $creator_id]);
            $group_id = $this->conn->lastInsertId();
            
            // 添加创建者为成员和群主
            $stmt = $this->conn->prepare("INSERT INTO group_members (group_id, user_id, is_admin) VALUES (?, ?, ?)");
            $stmt->execute([$group_id, $creator_id, true]);
            
            // 添加其他成员
            foreach ($member_ids as $member_id) {
                if ($member_id != $creator_id) {
                    $stmt = $this->conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
                    $stmt->execute([$group_id, $member_id]);
                }
            }
            
            $this->conn->commit();
            return $group_id;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Create group error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取群聊信息
     * @param int $group_id 群聊ID
     * @return array|false 群聊信息或false
     */
    public function getGroupInfo($group_id) {
        $stmt = $this->conn->prepare("SELECT * FROM groups WHERE id = ?");
        $stmt->execute([$group_id]);
        return $stmt->fetch();
    }
    
    /**
     * 获取群聊成员列表
     * @param int $group_id 群聊ID
     * @return array 成员列表
     */
    public function getGroupMembers($group_id) {
        $stmt = $this->conn->prepare("SELECT u.*, gm.is_admin FROM users u 
                                     JOIN group_members gm ON u.id = gm.user_id 
                                     WHERE gm.group_id = ?");
        $stmt->execute([$group_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * 添加群聊成员
     * @param int $group_id 群聊ID
     * @param array $user_ids 用户ID数组
     * @return bool 是否成功
     */
    public function addGroupMembers($group_id, $user_ids) {
        // 检查当前成员数量
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM group_members WHERE group_id = ?");
        $stmt->execute([$group_id]);
        $current_count = $stmt->fetch()['count'];
        
        // 验证总成员数不超过2000
        if ($current_count + count($user_ids) > 2000) {
            return false;
        }
        
        try {
            $this->conn->beginTransaction();
            
            $stmt = $this->conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
            foreach ($user_ids as $user_id) {
                $stmt->execute([$group_id, $user_id]);
            }
            
            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Add group members error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 设置管理员
     * @param int $group_id 群聊ID
     * @param int $user_id 用户ID
     * @param bool $is_admin 是否为管理员
     * @return bool 是否成功
     */
    public function setAdmin($group_id, $user_id, $is_admin = true) {
        // 检查管理员数量
        if ($is_admin) {
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM group_members WHERE group_id = ? AND is_admin = 1");
            $stmt->execute([$group_id]);
            $admin_count = $stmt->fetch()['count'];
            
            // 管理员数量不能超过9个（不包括群主）
            if ($admin_count >= 9) {
                return false;
            }
        }
        
        $stmt = $this->conn->prepare("UPDATE group_members SET is_admin = ? WHERE group_id = ? AND user_id = ?");
        return $stmt->execute([$is_admin, $group_id, $user_id]);
    }
    
    /**
     * 转让群主
     * @param int $group_id 群聊ID
     * @param int $current_owner_id 当前群主ID
     * @param int $new_owner_id 新群主ID
     * @return bool 是否成功
     */
    public function transferOwnership($group_id, $current_owner_id, $new_owner_id) {
        // 验证当前用户是群主
        $stmt = $this->conn->prepare("SELECT owner_id FROM groups WHERE id = ? AND owner_id = ?");
        $stmt->execute([$group_id, $current_owner_id]);
        if (!$stmt->fetch()) {
            return false;
        }
        
        // 验证新群主是群成员
        $stmt = $this->conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $new_owner_id]);
        if (!$stmt->fetch()) {
            return false;
        }
        
        try {
            $this->conn->beginTransaction();
            
            // 更新群主
            $stmt = $this->conn->prepare("UPDATE groups SET owner_id = ? WHERE id = ?");
            $stmt->execute([$new_owner_id, $group_id]);
            
            // 设置新群主为管理员
            $stmt = $this->conn->prepare("UPDATE group_members SET is_admin = 1 WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$group_id, $new_owner_id]);
            
            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Transfer ownership error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 发送群聊消息
     * @param int $group_id 群聊ID
     * @param int $sender_id 发送者ID
     * @param string $content 消息内容
     * @param array $file_info 文件信息（可选）
     * @return array 包含success和message_id的关联数组
     */
    public function sendGroupMessage($group_id, $sender_id, $content, $file_info = []) {
        // 验证发送者是群成员
        $stmt = $this->conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $sender_id]);
        if (!$stmt->fetch()) {
            return ['success' => false, 'message' => '发送者不是群成员'];
        }
        
        $file_path = isset($file_info['file_path']) ? $file_info['file_path'] : null;
        $file_name = isset($file_info['file_name']) ? $file_info['file_name'] : null;
        $file_size = isset($file_info['file_size']) ? $file_info['file_size'] : null;
        $file_type = isset($file_info['file_type']) ? $file_info['file_type'] : null;
        
        $stmt = $this->conn->prepare("INSERT INTO group_messages (group_id, sender_id, content, file_path, file_name, file_size, file_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$group_id, $sender_id, $content, $file_path, $file_name, $file_size, $file_type])) {
            $message_id = $this->conn->lastInsertId();
            return ['success' => true, 'message_id' => $message_id];
        }
        return ['success' => false, 'message' => '发送消息失败'];
    }
    
    /**
     * 获取群聊消息
     * @param int $group_id 群聊ID
     * @param int $user_id 用户ID（验证权限）
     * @param int $last_message_id 最后一条消息ID（用于分页）
     * @param int $limit 消息数量限制
     * @return array 消息列表
     */
    public function getGroupMessages($group_id, $user_id, $last_message_id = 0, $limit = 50) {
        // 验证用户是群成员
        $stmt = $this->conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        if (!$stmt->fetch()) {
            return [];
        }
        
        // 对于获取新消息，使用created_at而不是id，确保按时间顺序获取
        if ($last_message_id > 0) {
            // 获取指定消息的创建时间
            $stmt = $this->conn->prepare("SELECT created_at FROM group_messages WHERE id = ?");
            $stmt->execute([$last_message_id]);
            $last_message = $stmt->fetch();
            
            if ($last_message) {
                $where_clause = "AND created_at > ?";
                $stmt = $this->conn->prepare("SELECT gm.*, u.username, u.avatar FROM group_messages gm 
                                             JOIN users u ON gm.sender_id = u.id 
                                             WHERE gm.group_id = ? $where_clause 
                                             ORDER BY gm.created_at ASC 
                                             LIMIT ?");
                $stmt->execute([$group_id, $last_message['created_at'], $limit]);
                return $stmt->fetchAll();
            }
        }
        
        // 如果没有last_message_id或获取失败，返回最新的消息
        $stmt = $this->conn->prepare("SELECT gm.*, u.username, u.avatar FROM group_messages gm 
                                     JOIN users u ON gm.sender_id = u.id 
                                     WHERE gm.group_id = ? 
                                     ORDER BY gm.created_at ASC 
                                     LIMIT ?");
        $stmt->execute([$group_id, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * 撤回群聊消息
     * @param int $message_id 消息ID
     * @param int $user_id 操作用户ID
     * @return bool 是否成功
     */
    public function removeGroupMessage($message_id, $user_id) {
        // 获取消息信息
        $stmt = $this->conn->prepare("SELECT gm.*, g.owner_id FROM group_messages gm 
                                     JOIN groups g ON gm.group_id = g.id 
                                     WHERE gm.id = ?");
        $stmt->execute([$message_id]);
        $message = $stmt->fetch();
        
        if (!$message) {
            return false;
        }
        
        // 检查消息是否在2分钟内
        $message_time = strtotime($message['created_at']);
        $current_time = time();
        if (($current_time - $message_time) > 120) { // 120秒 = 2分钟
            return false;
        }
        
        // 检查操作权限：消息发送者、群主或管理员
        if ($user_id == $message['sender_id']) {
            // 发送者可以撤回自己的消息
            $can_remove = true;
        } else {
            // 检查是否是管理员或群主
            $stmt = $this->conn->prepare("SELECT is_admin FROM group_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$message['group_id'], $user_id]);
            $member = $stmt->fetch();
            
            $can_remove = $user_id == $message['owner_id'] || ($member && $member['is_admin']);
        }
        
        if ($can_remove) {
            $stmt = $this->conn->prepare("DELETE FROM group_messages WHERE id = ?");
            return $stmt->execute([$message_id]);
        }
        
        return false;
    }
    
    /**
     * 退出群聊
     * @param int $group_id 群聊ID
     * @param int $user_id 用户ID
     * @return bool 是否成功
     */
    public function leaveGroup($group_id, $user_id) {
        // 验证用户是群成员
        $stmt = $this->conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        if (!$stmt->fetch()) {
            return false;
        }
        
        // 群主不能直接退出，必须先转让群主
        $stmt = $this->conn->prepare("SELECT owner_id FROM groups WHERE id = ? AND owner_id = ?");
        $stmt->execute([$group_id, $user_id]);
        if ($stmt->fetch()) {
            return false;
        }
        
        $stmt = $this->conn->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
        return $stmt->execute([$group_id, $user_id]);
    }
    

    
    /**
     * 获取用户加入的群聊列表
     * @param int $user_id 用户ID
     * @return array 群聊列表
     */
    public function getUserGroups($user_id) {
        $stmt = $this->conn->prepare("SELECT g.*, (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count 
                                     FROM groups g 
                                     JOIN group_members gm ON g.id = gm.group_id 
                                     WHERE gm.user_id = ? 
                                     GROUP BY g.id");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * 获取群聊成员角色
     * @param int $group_id 群聊ID
     * @param int $user_id 用户ID
     * @return array|false 角色信息或false
     */
    public function getMemberRole($group_id, $user_id) {
        $stmt = $this->conn->prepare("SELECT gm.is_admin, g.owner_id FROM group_members gm 
                                     JOIN groups g ON gm.group_id = g.id 
                                     WHERE gm.group_id = ? AND gm.user_id = ?");
        $stmt->execute([$group_id, $user_id]);
        return $stmt->fetch();
    }
    
    /**
     * 获取所有群聊信息（管理员功能）
     * @return array 所有群聊列表
     */
    public function getAllGroups() {
        $stmt = $this->conn->prepare("SELECT g.*, 
                                            u1.username as creator_username, 
                                            u2.username as owner_username,
                                            (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
                                     FROM groups g
                                     JOIN users u1 ON g.creator_id = u1.id
                                     JOIN users u2 ON g.owner_id = u2.id
                                     ORDER BY g.created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 获取所有群聊消息（管理员功能）
     * @return array 所有群聊消息列表
     */
    public function getAllGroupMessages() {
        $stmt = $this->conn->prepare("SELECT gm.*, 
                                            u.username as sender_username,
                                            g.name as group_name
                                     FROM group_messages gm
                                     JOIN users u ON gm.sender_id = u.id
                                     JOIN groups g ON gm.group_id = g.id
                                     ORDER BY gm.created_at DESC
                                     LIMIT 1000"); // 限制1000条消息
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * 删除群聊
     * @param int $group_id 群聊ID
     * @param int|null $owner_id 群主ID（可选，为null时表示管理员直接删除）
     * @return bool 是否成功
     */
    public function deleteGroup($group_id, $owner_id = null) {
        // 如果提供了owner_id，验证该用户是否是群主
        if ($owner_id !== null) {
            $stmt = $this->conn->prepare("SELECT id FROM groups WHERE id = ? AND owner_id = ?");
            $stmt->execute([$group_id, $owner_id]);
            if (!$stmt->fetch()) {
                return false;
            }
        }
        
        try {
            $this->conn->beginTransaction();
            
            // 删除群聊消息
            $stmt = $this->conn->prepare("DELETE FROM group_messages WHERE group_id = ?");
            $stmt->execute([$group_id]);
            
            // 删除群聊成员
            $stmt = $this->conn->prepare("DELETE FROM group_members WHERE group_id = ?");
            $stmt->execute([$group_id]);
            
            // 删除群聊
            $stmt = $this->conn->prepare("DELETE FROM groups WHERE id = ?");
            $stmt->execute([$group_id]);
            
            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Delete group error: " . $e->getMessage());
            return false;
        }
    }
}
?>