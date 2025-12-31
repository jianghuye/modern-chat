<?php
// 启动会话
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '用户未登录']);
    exit;
}

// 检查是否有文件上传
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '请选择要上传的头像文件']);
    exit;
}

// 获取用户ID
$user_id = $_SESSION['user_id'];

// 允许的文件类型
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
// 允许的文件扩展名
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

// 获取文件信息
$file = $_FILES['avatar'];
$file_type = $file['type'];
$file_size = $file['size'];
$file_tmp = $file['tmp_name'];

// 获取文件扩展名
$file_info = pathinfo($file['name']);
$file_extension = strtolower($file_info['extension']);

// 检查文件类型
if (!in_array($file_type, $allowed_types) || !in_array($file_extension, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => '只允许上传JPG、PNG或GIF格式的图片']);
    exit;
}

// 检查文件大小（限制为5MB）
$max_size = 5 * 1024 * 1024;
if ($file_size > $max_size) {
    echo json_encode(['success' => false, 'message' => '图片大小不能超过5MB']);
    exit;
}

// 定义上传目录
$upload_dir = 'uploads/avatars/';

// 确保目录存在
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 生成唯一的文件名
$new_filename = $user_id . '_' . time() . '.' . $file_extension;
$file_path = $upload_dir . $new_filename;

// 处理图片，调整为32*32像素
list($original_width, $original_height) = getimagesize($file_tmp);

// 创建新的图片资源
$new_width = 32;
$new_height = 32;

// 根据文件类型创建图片资源
switch ($file_type) {
    case 'image/jpeg':
        $source_image = imagecreatefromjpeg($file_tmp);
        break;
    case 'image/png':
        $source_image = imagecreatefrompng($file_tmp);
        break;
    case 'image/gif':
        $source_image = imagecreatefromgif($file_tmp);
        break;
    default:
        echo json_encode(['success' => false, 'message' => '不支持的图片类型']);
        exit;
}

// 创建目标图片资源
$destination_image = imagecreatetruecolor($new_width, $new_height);

// 保留PNG和GIF的透明度
if ($file_type == 'image/png' || $file_type == 'image/gif') {
    imagealphablending($destination_image, false);
    imagesavealpha($destination_image, true);
    $transparent = imagecolorallocatealpha($destination_image, 255, 255, 255, 127);
    imagefilledrectangle($destination_image, 0, 0, $new_width, $new_height, $transparent);
}

// 调整图片大小
imagecopyresampled($destination_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

// 保存调整后的图片
switch ($file_type) {
    case 'image/jpeg':
        imagejpeg($destination_image, $file_path, 90);
        break;
    case 'image/png':
        imagepng($destination_image, $file_path, 9);
        break;
    case 'image/gif':
        imagegif($destination_image, $file_path);
        break;
}

// 释放图片资源
imagedestroy($source_image);
imagedestroy($destination_image);

// 连接数据库
$conn = new mysqli('localhost', 'root', '', 'chatroom');

// 检查数据库连接
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => '数据库连接失败']);
    exit;
}

// 更新用户头像
$avatar_url = $file_path;
$sql = "UPDATE users SET avatar = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $avatar_url, $user_id);

if ($stmt->execute()) {
    // 删除旧头像（如果存在）
    $sql = "SELECT avatar FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($old_avatar);
    $stmt->fetch();
    $stmt->close();
    
    if ($old_avatar && $old_avatar !== $avatar_url && file_exists($old_avatar)) {
        unlink($old_avatar);
    }
    
    echo json_encode(['success' => true, 'message' => '头像修改成功']);
} else {
    // 删除已上传的图片
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    echo json_encode(['success' => false, 'message' => '数据库更新失败：' . $conn->error]);
}

// 关闭数据库连接
$conn->close();
?>