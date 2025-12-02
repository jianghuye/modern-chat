<?php
require_once 'db.php';

class User {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // 用户注册
    public function register($username, $email, $password) {
        try {
            // 检查用户名和邮箱是否已存在
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => '用户名或邮箱已存在'];
            }
            
            // 哈希密码
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
            
            // 插入新用户
            $stmt = $this->conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hashedPassword]);
            
            return ['success' => true, 'message' => '注册成功', 'user_id' => $this->conn->lastInsertId()];
        } catch(PDOException $e) {
            error_log("Register Error: " . $e->getMessage());
            return ['success' => false, 'message' => '注册失败，请稍后重试'];
        }
    }
    
    // 用户登录
    public function login($email, $password) {
        try {
            // 查找用户
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => '邮箱或密码错误'];
            }
            
            // 检查用户是否被注销
            // 检查is_deleted字段
            if (isset($user['is_deleted']) && $user['is_deleted']) {
                return ['success' => false, 'message' => '账户已被管理员删除，无法登录'];
            }
            // 检查avatar字段是否为deleted_user
            if ($user['avatar'] === 'deleted_user') {
                return ['success' => false, 'message' => '账户已被管理员删除，无法登录'];
            }
            
            // 验证密码
            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => '邮箱或密码错误'];
            }
            
            // 登录时不立即更新状态为在线，等待页面加载后再更新
            // 这样可以确保只有页面真正加载后才显示在线状态
            
            return ['success' => true, 'user' => $user];
        } catch(PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            return ['success' => false, 'message' => '登录失败，请稍后重试'];
        }
    }
    
    // 更新用户状态
    public function updateStatus($user_id, $status) {
        try {
            $stmt = $this->conn->prepare("UPDATE users SET status = ?, last_active = NOW() WHERE id = ?");
            $stmt->execute([$status, $user_id]);
            return true;
        } catch(PDOException $e) {
            error_log("Update Status Error: " . $e->getMessage());
            return false;
        }
    }
    
    // 获取用户信息
    public function getUserById($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Get User By Id Error: " . $e->getMessage());
            return null;
        }
    }
    
    // 通过用户名搜索用户
    public function searchUsers($username, $current_user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT id, username, email, avatar, status FROM users WHERE username LIKE ? AND id != ?");
            $stmt->execute(["%$username%", $current_user_id]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Search Users Error: " . $e->getMessage());
            return [];
        }
    }
    
    // 检查用户是否在线
    public function isOnline($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT status FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            return $user ? $user['status'] == 'online' : false;
        } catch(PDOException $e) {
            error_log("Is Online Error: " . $e->getMessage());
            return false;
        }
    }
    
    // 获取用户状态
    public function getStatus($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT status FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            return $user ? $user['status'] : 'offline';
        } catch(PDOException $e) {
            error_log("Get Status Error: " . $e->getMessage());
            return 'offline';
        }
    }
    
    /**
     * 获取所有用户信息（管理员功能）
     * @return array 所有用户列表
     */
    public function getAllUsers() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users ORDER BY created_at DESC");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Get All Users Error: " . $e->getMessage());
            return [];
        }
    }
}
