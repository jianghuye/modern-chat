<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - Modern Chat</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .login-link {
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .login-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .error-message {
            background: #fee;
            color: #d32f2f;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #fcc;
        }
        
        .success-message {
            background: #e8f5e8;
            color: #388e3c;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #c8e6c9;
        }
        
        @media (max-width: 500px) {
            .container {
                margin: 20px;
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>创建账户</h1>
        
        <?php
        if (isset($_GET['error'])) {
            echo '<div class="error-message">' . htmlspecialchars($_GET['error']) . '</div>';
        }
        if (isset($_GET['success'])) {
            echo '<div class="success-message">' . htmlspecialchars($_GET['success']) . '</div>';
        }
        ?>
        
        <form action="register_process.php" method="POST">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required minlength="3" maxlength="50">
            </div>
            
            <div class="form-group">
                <label for="email">邮箱</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">确认密码</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            </div>
            
            <button type="submit" class="btn">注册</button>
        </form>
        
        <div class="login-link">
            已有账户？ <a href="login.php">立即登录</a>
        </div>
    </div>
    
    <!-- 邮箱验证弹窗 -->
    <div id="email-verify-modal" style="
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
        <div style="
            background: white;
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        ">
            <div id="verify-icon" style="
                font-size: 64px;
                margin-bottom: 20px;
            ">⏳</div>
            <h3 id="verify-title" style="
                margin-bottom: 15px;
                color: #333;
                font-size: 18px;
            ">正在验证邮箱</h3>
            <p id="verify-message" style="
                margin-bottom: 25px;
                color: #666;
                font-size: 14px;
                line-height: 1.5;
            ">正在验证邮箱，请稍后...</p>
            <button id="verify-close-btn" style="
                padding: 10px 25px;
                background: #667eea;
                color: white;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 500;
                font-size: 14px;
                transition: background-color 0.2s;
            ">确定</button>
        </div>
    </div>
    
    <script>
        // 初始化邮箱验证状态
        let emailVerified = false;
        let isVerifying = false;
        
        // 获取元素
        const emailInput = document.getElementById('email');
        const form = document.querySelector('form');
        const modal = document.getElementById('email-verify-modal');
        const verifyIcon = document.getElementById('verify-icon');
        const verifyTitle = document.getElementById('verify-title');
        const verifyMessage = document.getElementById('verify-message');
        const verifyCloseBtn = document.getElementById('verify-close-btn');
        
        // 关闭弹窗
        verifyCloseBtn.addEventListener('click', () => {
            modal.style.display = 'none';
            if (!emailVerified) {
                emailInput.focus();
            }
        });
        
        // 显示验证弹窗
        function showVerifyModal() {
            modal.style.display = 'flex';
            verifyIcon.textContent = '⏳';
            verifyTitle.textContent = '正在验证邮箱';
            verifyMessage.textContent = '正在验证邮箱，请稍后...';
            verifyCloseBtn.style.display = 'none';
        }
        
        // 显示验证结果弹窗
        function showVerifyResult(isSuccess, message) {
            modal.style.display = 'flex';
            verifyIcon.textContent = isSuccess ? '✅' : '❌';
            verifyTitle.textContent = isSuccess ? '验证成功' : '验证失败';
            verifyMessage.textContent = message;
            verifyCloseBtn.style.display = 'block';
            emailVerified = isSuccess;
        }
        
        // 邮箱验证函数
        async function verifyEmail(email) {
            // 检查是否为Gmail
            if (/^[a-zA-Z0-9._%+-]+@gmail\.com$/.test(email)) {
                // Gmail直接验证成功
                showVerifyResult(true, 'Gmail邮箱，允许继续操作');
                return true;
            }
            
            showVerifyModal();
            isVerifying = true;
            
            try {
                // 发送验证请求
                const response = await fetch('verify_email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `email=${encodeURIComponent(email)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showVerifyResult(true, '邮箱存在，允许继续操作');
                    return true;
                } else {
                    showVerifyResult(false, '邮箱不存在，请重新填写');
                    return false;
                }
            } catch (error) {
                console.error('邮箱验证失败:', error);
                showVerifyResult(false, '邮箱验证失败，请稍后重试');
                return false;
            } finally {
                isVerifying = false;
            }
        }
        
        // 监听邮箱输入框的blur事件
        emailInput.addEventListener('blur', async () => {
            const email = emailInput.value.trim();
            if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                await verifyEmail(email);
            }
        });
        
        // 阻止表单提交，除非邮箱已验证
        form.addEventListener('submit', (e) => {
            if (!emailVerified && isVerifying) {
                e.preventDefault();
                alert('正在验证邮箱，请稍后...');
            }
        });
    </script>
</body>
</html>