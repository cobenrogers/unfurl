<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Approval - Unfurl</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f7fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            max-width: 480px;
            width: 100%;
            padding: 48px 40px;
            text-align: center;
        }

        .icon {
            width: 64px;
            height: 64px;
            background: #fef3c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 32px;
        }

        h1 {
            font-size: 24px;
            color: #1a202c;
            margin-bottom: 12px;
        }

        p {
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .user-info {
            background: #f7fafc;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .user-info strong {
            color: #2d3748;
        }

        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        button, a {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: white;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #f7fafc;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">⏳</div>
        <h1>Account Pending Approval</h1>
        <p>Your account is waiting for administrator approval. You'll be able to access Unfurl once approved.</p>

        <?php if (isset($user)): ?>
        <div class="user-info">
            <strong><?= htmlspecialchars($user['email']) ?></strong>
        </div>
        <?php endif; ?>

        <div class="actions">
            <button onclick="location.reload()" class="btn-primary">Check Again</button>
            <a href="https://bennernet.com/auth/api/logout.php" class="btn-secondary">Sign Out</a>
        </div>
    </div>
</body>
</html>
