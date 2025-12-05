<?php
/**
 * 反馈处理页面
 * 处理反馈的提交和管理
 */
require_once 'config.php';
require_once 'db.php';
require_once 'Feedback-1.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$feedback = new Feedback($conn);

// 处理反馈提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'submit_feedback') {
        $content = $_POST['content'];
        $image_path = null;
        
        // 处理图片上传
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            
            // 验证文件类型
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_info = getimagesize($file['tmp_name']);
            
            if ($file_info && in_array($file_info['mime'], $allowed_types)) {
                // 验证文件大小（限制为5MB）
                $max_size = 5 * 1024 * 1024;
                if ($file['size'] <= $max_size) {
                    // 生成唯一文件名
                    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $new_filename = 'feedback_' . $user_id . '_' . time() . '.' . $file_ext;
                    $upload_dir = 'uploads/feedback/';
                    
                    // 创建目录如果不存在
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // 移动文件
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_filename)) {
                        $image_path = $upload_dir . $new_filename;
                    }
                }
            }
        }
        
        $result = $feedback->submitFeedback($user_id, $content, $image_path);
        echo json_encode($result);
        exit;
    } elseif ($_POST['action'] === 'mark_received' && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        $feedback_id = intval($_POST['feedback_id']);
        $result = $feedback->updateFeedbackStatus($feedback_id, 'received');
        echo json_encode($result);
        exit;
    } elseif ($_POST['action'] === 'delete_feedback' && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        $feedback_id = intval($_POST['feedback_id']);
        $result = $feedback->deleteFeedback($feedback_id);
        echo json_encode($result);
        exit;
    }
}

// 检查是否是管理员
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

// 如果是管理员，显示反馈管理页面
if ($is_admin) {
    $feedbacks = $feedback->getAllFeedback();
    include 'admin_feedback.php';
    exit;
}

// 如果是普通用户，重定向到聊天页面
header('Location: chat.php');
exit;
