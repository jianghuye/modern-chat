<?php
/**
 * Feedback 类
 * 处理反馈相关的数据库操作
 */
class Feedback {
    private $conn;
    
    /**
     * 构造函数
     * @param PDO $db 数据库连接对象
     */
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * 提交反馈
     * @param int $user_id 用户ID
     * @param string $content 反馈内容
     * @param string|null $image_path 图片路径
     * @return array 执行结果
     */
    public function submitFeedback($user_id, $content, $image_path = null) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO feedback (user_id, content, image_path) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $content, $image_path]);
            
            return [
                'success' => true,
                'message' => '反馈提交成功'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '反馈提交失败：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取所有反馈
     * @return array 反馈列表
     */
    public function getAllFeedback() {
        $stmt = $this->conn->prepare("SELECT f.*, u.username FROM feedback f JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取用户的反馈
     * @param int $user_id 用户ID
     * @return array 反馈列表
     */
    public function getUserFeedback($user_id) {
        $stmt = $this->conn->prepare("SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 更新反馈状态
     * @param int $feedback_id 反馈ID
     * @param string $status 状态值
     * @return array 执行结果
     */
    public function updateFeedbackStatus($feedback_id, $status) {
        try {
            $stmt = $this->conn->prepare("UPDATE feedback SET status = ? WHERE id = ?");
            $stmt->execute([$status, $feedback_id]);
            
            return [
                'success' => true,
                'message' => '反馈状态更新成功'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '反馈状态更新失败：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 删除反馈
     * @param int $feedback_id 反馈ID
     * @return array 执行结果
     */
    public function deleteFeedback($feedback_id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM feedback WHERE id = ?");
            $stmt->execute([$feedback_id]);
            
            return [
                'success' => true,
                'message' => '反馈删除成功'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '反馈删除失败：' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取未处理的反馈数量
     * @return int 未处理的反馈数量
     */
    public function getPendingFeedbackCount() {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM feedback WHERE status = 'pending'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
}
