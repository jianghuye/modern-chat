<?php
// 检查$feedbacks变量是否存在
if (!isset($feedbacks)) {
    die('错误：$feedbacks变量未定义！请通过feedback-2.php访问此页面。');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>反馈管理 - Modern Chat</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1 {
            margin-bottom: 20px;
            color: #667eea;
        }
        
        .feedback-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .feedback-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .feedback-item:last-child {
            border-bottom: none;
        }
        
        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .feedback-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .feedback-info {
            display: flex;
            gap: 15px;
            font-size: 14px;
            color: #666;
        }
        
        .status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status.received {
            background: #d4edda;
            color: #155724;
        }
        
        .feedback-content {
            margin: 15px 0;
            line-height: 1.6;
        }
        
        .feedback-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .feedback-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-danger {
            background: #ff6b6b;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 16px;
            background: #f8f9fa;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
            transition: all 0.2s ease;
        }
        
        .back-btn:hover {
            background: #e9ecef;
        }
    </style>
</head>
<body>
            <a href="https://github.com/LzdqesjG/modern-chat" class="github-corner" aria-label="View source on GitHub"><svg width="80" height="80" viewBox="0 0 250 250" style="fill:#151513; color:#fff; position: absolute; top: 0; border: 0; right: 0;" aria-hidden="true"><path d="M0,0 L115,115 L130,115 L142,142 L250,250 L250,0 Z"/><path d="M128.3,109.0 C113.8,99.7 119.0,89.6 119.0,89.6 C122.0,82.7 120.5,78.6 120.5,78.6 C119.2,72.0 123.4,76.3 123.4,76.3 C127.3,80.9 125.5,87.3 125.5,87.3 C122.9,97.6 130.6,101.9 134.4,103.2" fill="currentColor" style="transform-origin: 130px 106px;" class="octo-arm"/><path d="M115.0,115.0 C114.9,115.1 118.7,116.5 119.8,115.4 L133.7,101.6 C136.9,99.2 139.9,98.4 142.2,98.6 C133.8,88.0 127.5,74.4 143.8,58.0 C148.5,53.4 154.0,51.2 159.7,51.0 C160.3,49.4 163.2,43.6 171.4,40.1 C171.4,40.1 176.1,42.5 178.8,56.2 C183.1,58.6 187.2,61.8 190.9,65.4 C194.5,69.0 197.7,73.2 200.1,77.6 C213.8,80.2 216.3,84.9 216.3,84.9 C212.7,93.1 206.9,96.0 205.4,96.6 C205.1,102.4 203.0,107.8 198.3,112.5 C181.9,128.9 168.3,122.5 157.7,114.1 C157.9,116.9 156.7,120.9 152.7,124.9 L141.0,136.5 C139.8,137.7 141.6,141.9 141.8,141.8 Z" fill="currentColor" class="octo-body"/></svg></a><style>.github-corner:hover .octo-arm{animation:octocat-wave 560ms ease-in-out}@keyframes octocat-wave{0%,100%{transform:rotate(0)}20%,60%{transform:rotate(-25deg)}40%,80%{transform:rotate(10deg)}}@media (max-width:500px){.github-corner:hover .octo-arm{animation:none}.github-corner .octo-arm{animation:octocat-wave 560ms ease-in-out}}</style>
    <div class="container">
        <a href="admin.php" class="back-btn">← 返回管理首页</a>
        <h1>反馈管理</h1>
        
        <div class="feedback-list">
            <?php if (empty($feedbacks)): ?>
                <div class="empty-state">
                    <h3>暂无反馈</h3>
                    <p>目前还没有用户提交反馈</p>
                </div>
            <?php else: ?>
                <?php foreach ($feedbacks as $feedback): ?>
                    <div class="feedback-item">
                        <div class="feedback-header">
                            <div class="feedback-user">
                                <div class="user-avatar">
                                    <?php if (!empty($feedback['avatar'])): ?>
                                        <img src="<?php echo $feedback['avatar']; ?>" alt="<?php echo $feedback['username']; ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php echo substr($feedback['username'], 0, 2); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?php echo $feedback['username']; ?></strong>
                                </div>
                            </div>
                            <div class="feedback-info">
                                <span><?php echo date('Y-m-d H:i:s', strtotime($feedback['created_at'])); ?></span>
                                <span class="status <?php echo $feedback['status']; ?>">
                                    <?php echo $feedback['status'] === 'pending' ? '待处理' : '已收到'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="feedback-content">
                            <?php echo nl2br(htmlspecialchars($feedback['content'])); ?>
                        </div>
                        
                        <?php if (!empty($feedback['image_path'])): ?>
                            <img src="<?php echo $feedback['image_path']; ?>" alt="反馈图片" class="feedback-image">
                        <?php endif; ?>
                        
                        <div class="feedback-actions">
                            <?php if ($feedback['status'] === 'pending'): ?>
                                <button class="btn btn-primary" onclick="markReceived(<?php echo $feedback['id']; ?>)">反馈已收到</button>
                            <?php endif; ?>
                            <button class="btn btn-danger" onclick="deleteFeedback(<?php echo $feedback['id']; ?>)">删除反馈</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // 标记反馈为已收到
        function markReceived(feedbackId) {
            if (confirm('确定要标记此反馈为已收到吗？')) {
                fetch('feedback-2.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'mark_received',
                        feedback_id: feedbackId
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        location.reload();
                    } else {
                        alert(result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('操作失败，请稍后重试');
                });
            }
        }
        
        // 删除反馈
        function deleteFeedback(feedbackId) {
            if (confirm('确定要删除此反馈吗？')) {
                fetch('feedback-2.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'delete_feedback',
                        feedback_id: feedbackId
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        location.reload();
                    } else {
                        alert(result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('操作失败，请稍后重试');
                });
            }
        }
    </script>
</body>
</html>