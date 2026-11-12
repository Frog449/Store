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
            // จดจำฉัน: ขยาย lifetime cookie session เป็น 30 วัน
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
    <meta name="theme-color" content="#fdf8f8">
    <meta name="description" content="<?= h($pageDescription) ?>">
    <title><?= h($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 16px;
            background: linear-gradient(135deg, #f0f5fc 0%, #e6effa 100%);
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 36px 32px 30px;
            border: 1px solid #d4e2f4;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 16px 45px rgba(7, 88, 180, 0.08);
            animation: rise-in 0.4s both;
        }
        .login-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            text-decoration: none;
            color: inherit;
        }
        .login-brand img {
            height: 42px;
            width: auto;
            object-fit: contain;
        }
        .login-header h1 {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
        }
        .login-header p {
            margin: 0 0 24px;
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
        }
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .login-form label {
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #334155;
        }
        .login-form input {
            height: 44px;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 13px;
            background: #fff;
            color: #1e293b;
            transition: all 0.2s;
        }
        .login-form input:focus {
            outline: none;
            border-color: #0758b4;
            box-shadow: 0 0 0 3px rgba(7, 88, 180, 0.15);
        }
        .login-submit {
            height: 44px;
            margin-top: 8px;
            border: none;
            border-radius: 7px;
            color: #fff;
            background: linear-gradient(105deg, #0758b4, #05448c);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(7, 88, 180, 0.22);
            transition: all 0.2s;
        }
        .login-submit:hover {
            background: linear-gradient(105deg, #05448c, #032d5e);
            transform: translateY(-1px);
        }
        .login-error {
            margin-bottom: 18px;
            padding: 10px 14px;
            border-radius: 6px;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid #fecaca;
        }
        .login-security-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 9px;
            border-radius: 4px;
            background: #e2e8f0;
            color: #475569;
            font-size: 10px;
            font-weight: 600;
            margin-bottom: 14px;
        }
        .login-remember {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            margin-top: -4px;
        }
        .login-remember input[type=checkbox] {
            width: 15px;
            height: 15px;
            accent-color: #0758b4;
            cursor: pointer;
            flex-shrink: 0;
        }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <a class="login-brand" href="login.php">
            <img src="assets/logo.png" alt="AUT Automation Logo">
            <span class="brand-name">Store <span>Automeation</span><small>INDUSTRIAL SUPPLY</small></span>
        </a>

        <div class="login-header">
            <span class="login-security-tag">🔒 INTERNAL ACCESS ONLY</span>
            <h1>เข้าสู่ระบบคลังสินค้า</h1>
            <p>กรุณากรอกชื่อผู้ใช้ รหัสพนักงาน หรืออีเมล และรหัสผ่านเพื่อเข้าใช้งานระบบ</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="login-error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form class="login-form" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <label>
                ชื่อผู้ใช้งาน, รหัสพนักงาน หรือ อีเมล
                <input type="text" name="username" placeholder="ระบุ username, รหัสพนักงาน หรือ อีเมล..." required autofocus value="<?= h((string) ($_POST['username'] ?? '')) ?>">
            </label>

            <label>
                รหัสผ่าน (Password)
                <input type="password" name="password" placeholder="ระบุรหัสผ่าน..." required>
            </label>

            <button class="login-submit" type="submit">เข้าสู่ระบบ</button>

            <label class="login-remember">
                <input type="checkbox" name="remember_me" value="1">
                จดจำฉัน (30 วัน)
            </label>
        </form>


    </div>
</div>
</body>
</html>

