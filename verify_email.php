<?php
// 设置响应类型为JSON
header('Content-Type: application/json');

// 包含必要的文件
require_once 'config.php';
require_once 'db.php';

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 获取邮箱参数
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

// 验证邮箱格式
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => '无效的邮箱格式']);
    exit;
}

// 检查是否启用邮箱验证
$email_verify = getConfig('email_verify', false);

if (!$email_verify) {
    // 未启用邮箱验证，直接返回成功
    echo json_encode(['success' => true, 'message' => '邮箱验证功能未启用']);
    exit;
}

// 判断邮箱是否为Gmail
$is_gmail = preg_match('/@gmail\.com$/i', $email);

if ($is_gmail) {
    // Gmail邮箱，直接返回成功
    echo json_encode(['success' => true, 'message' => 'Gmail邮箱，直接验证通过']);
    exit;
}

// 非Gmail邮箱，使用API验证
$api_url = getConfig('email_verify_api', 'https://api.nbhao.org/v1/email/verify');
$request_method = strtoupper(getConfig('email_verify_api_Request', 'POST'));
$verify_param = getConfig('email_verify_api_Verify_parameters', 'result');

// 准备请求数据
$request_data = [
    'email' => $email
];

// 初始化cURL
$ch = curl_init();

// 设置cURL选项
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 禁用SSL验证，根据实际情况调整
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 禁用SSL主机验证，根据实际情况调整

if ($request_method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request_data));
} else {
    // GET请求，将参数添加到URL
    $api_url .= '?' . http_build_query($request_data);
    curl_setopt($ch, CURLOPT_URL, $api_url);
}

// 设置请求头
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json'
]);

// 执行请求并获取响应
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// cURL 资源会在不再被引用时自动关闭，无需显式调用 curl_close()

if ($http_code === 200) {
    // 解析响应
    $response_data = json_decode($response, true);
    
    if ($response_data) {
        // 提取验证结果
        $result_value = null;
        
        // 处理嵌套参数，如data.[0].result
        $param_path = explode('.', $verify_param);
        $temp_data = $response_data;
        $param_valid = true;
        
        foreach ($param_path as $param_part) {
            // 处理数组索引，如[0]
            if (preg_match('/^(.*?)\[(\d+)\]$/', $param_part, $matches)) {
                $key = $matches[1];
                $index = (int)$matches[2];
                
                if (isset($temp_data[$key]) && is_array($temp_data[$key]) && isset($temp_data[$key][$index])) {
                    $temp_data = $temp_data[$key][$index];
                } else {
                    $param_valid = false;
                    break;
                }
            } else {
                // 普通键
                if (isset($temp_data[$param_part])) {
                    $temp_data = $temp_data[$param_part];
                } else {
                    $param_valid = false;
                    break;
                }
            }
        }
        
        if ($param_valid) {
            $result_value = $temp_data;
        }
        
        // 检查验证结果
        $lower_result = $result_value ? strtolower($result_value) : '';
        if ($lower_result === 'true' || $lower_result === 'ok') {
            echo json_encode(['success' => true, 'message' => '邮箱存在，允许继续操作']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => '邮箱不存在，请重新填写']);
            exit;
        }
    } else {
        // 无法解析响应
        error_log('邮箱验证API响应解析失败: ' . $response);
        echo json_encode(['success' => false, 'message' => '邮箱验证失败，请稍后重试']);
        exit;
    }
} else {
    // API请求失败
    error_log('邮箱验证API请求失败，HTTP状态码: ' . $http_code);
    echo json_encode(['success' => false, 'message' => '邮箱验证失败，请稍后重试']);
    exit;
}
?>