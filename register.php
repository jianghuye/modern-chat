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
            font-family: 'Microsoft YaHei', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.15);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            animation: messageSlide 0.6s ease-out;
            border: 2px solid transparent;
        }
        
        @keyframes messageSlide {
            from {
                opacity: 0;
                transform: translateY(20px);
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
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #12b7f5;
            background: white;
            box-shadow: 0 0 0 3px rgba(18, 183, 245, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #12b7f5 0%, #00a2e8 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            box-shadow: 0 5px 18px rgba(18, 183, 245, 0.5);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(18, 183, 245, 0.6);
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
            color: #12b7f5;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .login-link a:hover {
            color: #00a2e8;
            text-decoration: underline;
        }
        
        .error-message {
            background: rgba(255, 77, 79, 0.1);
            color: #ff4d4f;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 2px solid rgba(255, 77, 79, 0.2);
        }
        
        .success-message {
            background: rgba(158, 234, 106, 0.1);
            color: #52c41a;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 2px solid rgba(158, 234, 106, 0.2);
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
        
        <form action="register_process.php" method="POST" onsubmit="return handleRegisterSubmit(this);">
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
            
            <!-- 极验验证码容器 -->
            <div class="form-group">
                <div id="captcha"></div>
            </div>
            
            <!-- 极验验证结果隐藏字段 -->
            <input type="hidden" name="geetest_challenge" id="geetest_challenge">
            <input type="hidden" name="geetest_validate" id="geetest_validate">
            <input type="hidden" name="geetest_seccode" id="geetest_seccode">
            
            <!-- 浏览器指纹隐藏字段 -->
            <input type="hidden" name="browser_fingerprint" id="browser_fingerprint">
            
            <button type="submit" class="btn">注册</button>
        </form>
        
        <div class="login-link">
            已有账户？ <a href="login.php">立即登录</a>
        </div>
    </div>
    
    <!-- 极验验证码JS库 -->
    <script src="https://static.geetest.com/v4/gt4.js"></script>
    
    <script>
        // 极验验证码初始化
        let geetestCaptcha = null;
        
        // 初始化极验验证码
        initGeetest4({
            captchaId: '55574dfff9c40f2efeb5a26d6d188245'
        }, function (captcha) {
            // captcha为验证码实例
            geetestCaptcha = captcha;
            captcha.appendTo("#captcha");// 调用appendTo将验证码插入到页的某一个元素中
        });
        
        // 浏览器指纹生成功能
        function generateBrowserFingerprint() {
            // 收集浏览器信息
            const fingerprintData = {
                userAgent: navigator.userAgent,
                screenResolution: screen.width + 'x' + screen.height,
                colorDepth: screen.colorDepth,
                timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                language: navigator.language,
                platform: navigator.platform,
                cookieEnabled: navigator.cookieEnabled,
                localStorageEnabled: typeof(Storage) !== 'undefined' && typeof(Storage.prototype.getItem) === 'function',
                sessionStorageEnabled: typeof(Storage) !== 'undefined' && typeof(Storage.prototype.getItem) === 'function',
                plugins: Array.from(navigator.plugins).map(plugin => plugin.name + ' ' + plugin.version).join(','),
                hardwareConcurrency: navigator.hardwareConcurrency || 0,
                deviceMemory: navigator.deviceMemory || 0
            };
            
            // 将数据转换为字符串
            const fingerprintString = JSON.stringify(fingerprintData);
            
            // 使用SHA-256生成哈希值
            return crypto.subtle.digest('SHA-256', new TextEncoder().encode(fingerprintString))
                .then(hashBuffer => {
                    // 将ArrayBuffer转换为十六进制字符串
                    const hashArray = Array.from(new Uint8Array(hashBuffer));
                    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                    return hashHex;
                });
        }
        
        // 表单提交处理
        async function handleRegisterSubmit(form) {
            // 检查极验验证码是否通过
            if (!geetestCaptcha || !geetestCaptcha.getValidate()) {
                alert('请完成验证码验证');
                return false;
            }
            
            // 获取验证码验证结果
            const validate = geetestCaptcha.getValidate();
            if (validate) {
                // 极验4.0返回的参数
                document.getElementById('geetest_challenge').value = validate.lot_number;
                document.getElementById('geetest_validate').value = validate.captcha_output;
                document.getElementById('geetest_seccode').value = validate.pass_token;
                
                // 添加新的隐藏字段用于极验4.0二次校验
                const genTimeInput = document.createElement('input');
                genTimeInput.type = 'hidden';
                genTimeInput.name = 'gen_time';
                genTimeInput.value = validate.gen_time;
                form.appendChild(genTimeInput);
                
                const captchaIdInput = document.createElement('input');
                captchaIdInput.type = 'hidden';
                captchaIdInput.name = 'captcha_id';
                captchaIdInput.value = '55574dfff9c40f2efeb5a26d6d188245';
                form.appendChild(captchaIdInput);
            }
            
            // 生成浏览器指纹
            const fingerprintInput = document.getElementById('browser_fingerprint');
            if (!fingerprintInput.value) {
                const fingerprint = await generateBrowserFingerprint();
                fingerprintInput.value = fingerprint;
            }
            return true;
        }
    </script>
</body>
</html>