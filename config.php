<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'chat');
define('DB_USER', 'root');
define('DB_PASS', 'cf211396ab9363ad');

// 应用配置
define('APP_NAME', 'Modern Chat');
define('APP_URL', 'http://localhost/chat');

// 上传配置
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 150 * 1024 * 1024); // 150MB

define('ALLOWED_FILE_TYPES', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain', 'text/csv',
    'video/mp4', 'video/webm', 'video/ogg',
    'audio/mpeg', 'audio/wav', 'audio/ogg'
]);

// 会话配置
define('SESSION_TIMEOUT', 3600); // 1小时

// 安全配置
define('HASH_ALGO', PASSWORD_DEFAULT);
define('HASH_COST', 12);

// 错误报告配置
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 启用会话
if (!isset($_SESSION)) {
    session_start();
}
