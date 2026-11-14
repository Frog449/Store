<?php
declare(strict_types=1);

define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/bootstrap.php';

if (auth_user() !== null) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'เข้าสู่ระบบ | Store Automeation';
$pageDescription = 'เข้าสู่ระบบคลังสินค้า Store Automeation สำหรับพนักงานและผู้ดูแลระบบ';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        inventory_require_csrf();
        $identity = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($identity === '' || $password === '') {
            $error = 'กรุณากรอกชื่อผู้ใช้ รหัสพนักงาน หรืออีเมล และรหัสผ่าน';
        } elseif (auth_login($identity, $password)) {
            if (!empty($_POST['remember_me'])) {
                $cookieParams = session_get_cookie_params();
                setcookie(
                    session_name(),
                    session_id(),
                    time() + 30 * 24 * 3600,
                    $cookieParams['path'],
                    $cookieParams['domain'],
                    $cookieParams['secure'],
                    $cookieParams['httponly']
                );
            }
            $redirectTo = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirectTo);
            exit;
        } else {
            $error = 'ชื่อผู้ใช้ รหัสพนักงาน อีเมล หรือรหัสผ่านไม่ถูกต้อง';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0758b4">
    <title><?= h($pageTitle) ?></title>
    <link rel="manifest" href="manifest.json">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: "Noto Sans Thai", "DM Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f0f4f9;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-wrap {
            width: 100%;
            max-width: 480px;
            padding: 24px 16px;
            display: flex;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 40px 36px 34px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.04);
            text-align: left;
        }
        .login-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
            text-decoration: none;
            color: inherit;
        }
        .login-brand img {
            height: 38px;
            width: auto;
            object-fit: contain;
        }
        .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
        }
        .brand-name {
            font-size: 17px;
            font-weight: 700;
            color: #0758b4;
        }
        .brand-sub {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1.2px;
            color: #94a3b8;
        }
        .security-badge-wrap {
            display: flex;
            justify-content: flex-start;
            margin-bottom: 16px;
        }
        .security-badge {
            display: inline-block;
            background: #e0f2fe;
            color: #0284c7;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.6px;
            padding: 4px 12px;
            border-radius: 6px;
            text-transform: uppercase;
        }
        .login-header h1 {
            margin: 0 0 6px;
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }
        .login-header p {
            margin: 0 0 24px;
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
        }
        .login-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 18px;
        }
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #334155;
        }
        .form-group input {
            height: 44px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 14px;
            font-size: 14px;
            color: #1e293b;
            background: #ffffff;
            transition: border-color 0.15s, box-shadow 0.15s;
            font-family: inherit;
        }
        .form-group input:focus {
            outline: none;
            border-color: #0758b4;
            box-shadow: 0 0 0 3px rgba(7, 88, 180, 0.15);
        }
        .form-group input::placeholder {
            color: #94a3b8;
            font-size: 13px;
        }
        .login-submit-btn {
            height: 46px;
            margin-top: 6px;
            border: none;
            border-radius: 8px;
            background: #004bbb;
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
            font-family: inherit;
        }
        .login-submit-btn:hover {
            background: #003b8e;
        }
        .login-remember {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
            color: #64748b;
            cursor: pointer;
            margin-top: 4px;
        }
        .login-remember input {
            width: 16px;
            height: 16px;
            accent-color: #004bbb;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <a class="login-brand" href="login.php">
                <img src="assets/logo.png" alt="AUT Automation">
                <div class="brand-text">
                    <span class="brand-name">Store Automeation</span>
                    <span class="brand-sub">INDUSTRIAL SUPPLY</span>
                </div>
            </a>

            <div class="security-badge-wrap">
                <span class="security-badge">INTERNAL ACCESS ONLY</span>
            </div>

            <div class="login-header">
                <h1>เข้าสู่ระบบคลังสินค้า</h1>
                <p>กรุณากรอกชื่อผู้ใช้ รหัสพนักงาน หรืออีเมล และรหัสผ่านเพื่อเข้าใช้งานระบบ</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert"><?= h($error) ?></div>
            <?php endif; ?>

            <form class="login-form" method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div class="form-group">
                    <label for="username">ชื่อผู้ใช้งาน, รหัสพนักงาน หรือ อีเมล</label>
                    <input type="text" id="username" name="username" placeholder="ระบุ username, รหัสพนักงาน หรือ อีเมล..." required autofocus value="<?= h((string) ($_POST['username'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label for="password">รหัสผ่าน (Password)</label>
                    <input type="password" id="password" name="password" placeholder="ระบุรหัสผ่าน..." required>
                </div>

                <button class="login-submit-btn" type="submit">เข้าสู่ระบบ</button>

                <label class="login-remember">
                    <input type="checkbox" name="remember_me" value="1">
                    จดจำฉัน (30 วัน)
                </label>
            </form>
        </div>
    </div>
</body>
</html>
