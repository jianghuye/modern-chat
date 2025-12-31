<?php
// 启用错误报告以便调试
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 设置错误日志
ini_set('error_log', 'error.log');

require_once 'db.php';

$error_message = '';
$success_message = '';
$security_question = '';
$user_id = null;
$show_security_modal = false;

// 检查错误次数限制表是否存在
function createSecurityAttemptsTable() {
    global $conn;
    if (!$conn) {
        error_log("Database connection is null in createSecurityAttemptsTable");
        return;
    }
    try {
        $sql = "
        CREATE TABLE IF NOT EXISTS security_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_success BOOLEAN DEFAULT FALSE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $conn->exec($sql);
    } catch (PDOException $e) {
        error_log("Create security_attempts table error: " . $e->getMessage());
    }
}

createSecurityAttemptsTable();

// 获取用户在指定时间内的错误尝试次数
function getFailedAttempts($user_id, $time_window = 3600) {
    global $conn;
    if (!$conn) {
        error_log("Database connection is null in getFailedAttempts");
        return 0;
    }
    try {
        $sql = "SELECT COUNT(*) as count FROM security_attempts WHERE user_id = ? AND is_success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $time_window]);
        $result = $stmt->fetch();
        return $result['count'];
    } catch (PDOException $e) {
        error_log("Get failed attempts error: " . $e->getMessage());
        return 0;
    }
}

// 记录尝试
function logAttempt($user_id, $is_success = false) {
    global $conn;
    if (!$conn) {
        error_log("Database connection is null in logAttempt");
        return;
    }
    try {
        $sql = "INSERT INTO security_attempts (user_id, is_success) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id, $is_success]);
    } catch (PDOException $e) {
        error_log("Log attempt error: " . $e->getMessage());
    }
}

// 处理获取密保问题请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_security_question') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    
    if (empty($username) || empty($email)) {
        $error_message = '请填写用户名和邮箱';
    } elseif (!$conn) {
        error_log("Database connection is null in get_security_question action");
        $error_message = '数据库连接失败，请稍后重试';
    } else {
        try {
            $stmt = $conn->prepare("SELECT id, has_security_question, security_question FROM users WHERE username = ? AND email = ?");
            $stmt->execute([$username, $email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error_message = '用户名和邮箱不匹配';
            } elseif (!$user['has_security_question']) {
                $error_message = '该账号未设置密保问题，请联系管理员';
            } else {
                // 检查错误尝试次数
                $failed_attempts = getFailedAttempts($user['id']);
                if ($failed_attempts >= 10) {
                    $error_message = '由于密保问题答案多次错误，请冷静一下，好好想想再试';
                } else {
                    $security_question = $user['security_question'];
                    $user_id = $user['id'];
                    $show_security_modal = true;
                }
            }
        } catch (PDOException $e) {
            error_log("Get security question error: " . $e->getMessage());
            $error_message = '获取密保问题失败，请稍后重试';
        }
    }
}

// 处理密保问题验证请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_security_answer') {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $security_answer = trim($_POST['security_answer']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if (empty($user_id) || empty($security_answer) || empty($new_password) || empty($confirm_password)) {
        $error_message = '请填写所有必填字段';
    } elseif ($new_password !== $confirm_password) {
        $error_message = '两次输入的密码不一致';
    } elseif (!$conn) {
        error_log("Database connection is null in verify_security_answer action");
        $error_message = '数据库连接失败，请稍后重试';
    } else {
        // 检查密码复杂度
        $complexity = 0;
        if (preg_match('/[a-z]/', $new_password)) $complexity++;
        if (preg_match('/[A-Z]/', $new_password)) $complexity++;
        if (preg_match('/\d/', $new_password)) $complexity++;
        if (preg_match('/[^a-zA-Z0-9]/', $new_password)) $complexity++;
        
        if ($complexity < 2) {
            $error_message = '密码不符合安全要求，请包含至少2种字符类型（大小写字母、数字、特殊符号）';
        } else {
            try {
                // 检查错误尝试次数
                $failed_attempts = getFailedAttempts($user_id);
                if ($failed_attempts >= 10) {
                    $error_message = '由于密保问题答案多次错误，请冷静一下，好好想想再试';
                } else {
                    // 获取用户信息
                    $stmt = $conn->prepare("SELECT security_answer FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if (!$user) {
                        $error_message = '用户不存在';
                    } else {
                        // 验证密保答案
                        if (password_verify($security_answer, $user['security_answer'])) {
                            // 验证成功，更新密码
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => 12]);
                            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $stmt->execute([$hashed_password, $user_id]);
                            
                            // 记录成功尝试
                            logAttempt($user_id, true);
                            
                            // 显示成功消息，10秒后跳转
                            $success_message = '密码已更改，请使用新密码登录';
                        } else {
                            // 验证失败，记录错误尝试
                            logAttempt($user_id, false);
                            
                            // 获取更新后的错误尝试次数
                            $failed_attempts = getFailedAttempts($user_id);
                            $remaining_attempts = 10 - $failed_attempts;
                            
                            if ($failed_attempts >= 10) {
                                $error_message = '由于密保问题答案多次错误，请冷静一下，好好想想再试';
                            } else {
                                $error_message = "你还有{$remaining_attempts}次填写机会";
                                // 重新获取密保问题，显示弹窗
                                $stmt = $conn->prepare("SELECT security_question FROM users WHERE id = ?");
                                $stmt->execute([$user_id]);
                                $user_info = $stmt->fetch();
                                $security_question = $user_info['security_question'];
                                $show_security_modal = true;
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Verify security answer error: " . $e->getMessage());
                $error_message = '验证失败，请稍后重试';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>忘记密码 - Modern Chat</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Microsoft YaHei', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #4285f4 0%, #1a73e8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #333;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 400px;
        }
        
        h1 {
            text-align: center;
            color: #12b7f5;
            margin-bottom: 30px;
            font-size: 24px;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus {
            border-color: #12b7f5;
            box-shadow: 0 0 0 3px rgba(18, 183, 245, 0.1);
            background: white;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #12b7f5 0%, #00a2e8 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(18, 183, 245, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(18, 183, 245, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(18, 183, 245, 0.3);
        }
        
        .error-message {
            background: #fff5f5;
            color: #ff4d4f;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #ffccc7;
        }
        
        .success-message {
            background: #f6ffed;
            color: #52c41a;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #b7eb8f;
        }
        
        .password-requirements {
            margin-top: 8px;
            color: #666;
            font-size: 12px;
            margin-bottom: 20px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .back-link a {
            color: #12b7f5;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-link a:hover {
            color: #00a2e8;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>忘记密码</h1>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <form id="forget-password-form" action="forgetpassword.php" method="POST">
            <input type="hidden" name="action" value="get_security_question">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="email">绑定邮箱</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div id="password-fields" style="display: none;">
                <div class="form-group">
                    <label for="new_password">新密码</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">确认新密码</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <p class="password-requirements">密码必须包含至少2种字符类型（大小写字母、数字、特殊符号）</p>
            </div>
            
            <button type="submit" class="btn">获取密保问题</button>
        </form>
        
        <div class="back-link">
            <a href="login.php">返回登录页面</a>
        </div>
    </div>
    
    <!-- 密保问题弹窗 -->
    <div id="security-modal" class="modal" style="
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    ">
        <div class="modal-content" style="
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        ">
            <h3 style="
                margin-bottom: 20px;
                color: #333;
                font-size: 18px;
                font-weight: 600;
                text-align: center;
            ">填写密保问题</h3>
            
            <form id="security-form" action="forgetpassword.php" method="POST">
                <input type="hidden" name="action" value="verify_security_answer">
                <input type="hidden" name="user_id" id="modal-user-id" value="<?php echo $user_id; ?>">
                
                <div class="form-group">
                    <label for="security_answer">密保问题</label>
                    <div style="
                        background: #f8f9fa;
                        padding: 14px;
                        border: 1px solid #eaeaea;
                        border-radius: 12px;
                        margin-bottom: 20px;
                        font-size: 14px;
                        color: #333;
                    "><?php echo htmlspecialchars($security_question); ?></div>
                </div>
                
                <div class="form-group">
                    <label for="security_answer">答案</label>
                    <input type="text" id="security_answer" name="security_answer" required>
                </div>
                
                <div class="form-group">
                    <label for="modal-new-password">新密码</label>
                    <input type="password" id="modal-new-password" name="new_password" required>
                </div>
                
                <div class="form-group">
                    <label for="modal-confirm-password">确认新密码</label>
                    <input type="password" id="modal-confirm-password" name="confirm_password" required>
                </div>
                
                <p class="password-requirements">密码必须包含至少2种字符类型（大小写字母、数字、特殊符号）</p>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn" style="flex: 1;">确定</button>
                    <button type="button" id="close-modal-btn" class="btn" style="
                        background: #f8f9fa;
                        color: #333;
                        border: 1px solid #eaeaea;
                        box-shadow: none;
                    ">取消</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 成功跳转弹窗 -->
    <div id="success-modal" class="modal" style="
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    ">
        <div class="modal-content" style="
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            text-align: center;
        ">
            <div style="font-size: 64px; margin-bottom: 20px;">✅</div>
            <h3 style="
                margin-bottom: 15px;
                color: #333;
                font-size: 18px;
                font-weight: 600;
            ">密码已更改</h3>
            <p style="
                margin-bottom: 30px;
                color: #666;
                font-size: 14px;
            ">密码已更改，请使用新密码登录</p>
            <div style="
                background: #f8f9fa;
                padding: 15px;
                border-radius: 12px;
                margin-bottom: 30px;
                font-size: 16px;
                font-weight: 600;
                color: #12b7f5;
            ">还有 <span id="countdown">10</span> 秒跳转到登录页面</div>
            <button id="success-close-btn" class="btn" onclick="window.location.href='login.php'">立即登录</button>
        </div>
    </div>
    
    <!-- 错误次数限制弹窗 -->
    <div id="limit-modal" class="modal" style="
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    ">
        <div class="modal-content" style="
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            text-align: center;
        ">
            <div style="font-size: 64px; margin-bottom: 20px;">⏰</div>
            <h3 style="
                margin-bottom: 15px;
                color: #333;
                font-size: 18px;
                font-weight: 600;
            ">密保问题多次错误</h3>
            <p style="
                margin-bottom: 30px;
                color: #666;
                font-size: 14px;
                line-height: 1.5;
            ">由于密保问题答案多次错误，请冷静一下，好好想想再添加</p>
            <div style="
                background: #f8f9fa;
                padding: 15px;
                border-radius: 12px;
                margin-bottom: 30px;
                font-size: 16px;
                font-weight: 600;
                color: #ff4d4f;
            ">还有 <span id="limit-countdown">3600</span> 秒可继续填写</div>
            <button id="limit-close-btn" class="btn">确定</button>
        </div>
    </div>
    
    <script>
        // 显示密保问题弹窗
        <?php if ($show_security_modal): ?>
            document.getElementById('security-modal').style.display = 'flex';
        <?php endif; ?>
        
        // 显示成功弹窗并开始倒计时
        <?php if (!empty($success_message)): ?>
            document.getElementById('success-modal').style.display = 'flex';
            let countdown = 10;
            const countdownElement = document.getElementById('countdown');
            const countdownInterval = setInterval(() => {
                countdown--;
                countdownElement.textContent = countdown;
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = 'login.php';
                }
            }, 1000);
        <?php endif; ?>
        
        // 关闭弹窗
        document.getElementById('close-modal-btn').addEventListener('click', () => {
            document.getElementById('security-modal').style.display = 'none';
        });
        
        document.getElementById('success-close-btn').addEventListener('click', () => {
            window.location.href = 'login.php';
        });
        
        document.getElementById('limit-close-btn').addEventListener('click', () => {
            document.getElementById('limit-modal').style.display = 'none';
        });
        
        // 点击弹窗外部关闭弹窗
        window.addEventListener('click', (e) => {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
        
        // 处理错误次数限制
        <?php 
        if (isset($user_id)) {
            $failed_attempts = getFailedAttempts($user_id);
            if ($failed_attempts >= 10) {
                echo "
                    document.getElementById('limit-modal').style.display = 'flex';
                    let limitCountdown = 3600;
                    const limitCountdownElement = document.getElementById('limit-countdown');
                    const limitCountdownInterval = setInterval(() => {
                        limitCountdown--;
                        limitCountdownElement.textContent = limitCountdown;
                        if (limitCountdown <= 0) {
                            clearInterval(limitCountdownInterval);
                            document.getElementById('limit-modal').style.display = 'none';
                        }
                    }, 1000);
                ";
            }
        }
        ?>
    </script>
</body>
</html>