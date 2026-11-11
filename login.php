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
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
        } elseif (auth_login($username, $password)) {
            $redirectTo = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirectTo);
            exit;
        } else {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
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
            background: linear-gradient(135deg, #fbf5f6 0%, #f6e8ea 100%);
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 36px 32px 30px;
            border: 1px solid #e8dfe1;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 16px 45px rgba(153, 28, 47, 0.08);
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
        .login-header h1 {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 700;
            color: #292326;
        }
        .login-header p {
            margin: 0 0 24px;
            font-size: 12px;
            color: #625b5e;
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
            color: #40383b;
        }
        .login-form input {
            height: 44px;
            padding: 0 14px;
            border: 1px solid #e3d8db;
            border-radius: 7px;
            font-size: 13px;
            background: #fff;
            color: #292326;
            transition: all 0.2s;
        }
        .login-form input:focus {
            outline: none;
            border-color: #ef233c;
            box-shadow: 0 0 0 3px rgba(239, 35, 60, 0.15);
        }
        .login-submit {
            height: 44px;
            margin-top: 8px;
            border: none;
            border-radius: 7px;
            color: #fff;
            background: linear-gradient(105deg, #ef233c, #b5172b);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(239, 35, 60, 0.22);
            transition: all 0.2s;
        }
        .login-submit:hover {
            background: linear-gradient(105deg, #d90429, #8d1124);
            transform: translateY(-1px);
        }
        .login-hint {
            margin-top: 24px;
            padding: 12px 14px;
            border-radius: 7px;
            border: 1px dashed #d66b77;
            background: #fff5f6;
            color: #70202c;
            font-size: 11px;
            line-height: 1.6;
            text-align: center;
        }
        .login-hint code {
            font-weight: 700;
            color: #ef233c;
            background: #ffe3e6;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        .login-error {
            margin-bottom: 18px;
            padding: 10px 14px;
            border-radius: 6px;
            background: #ffebee;
            color: #c62828;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid #ffcdd2;
        }
        .login-security-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 9px;
            border-radius: 4px;
            background: #f0edef;
            color: #625b5e;
            font-size: 10px;
            font-weight: 600;
            margin-bottom: 14px;
        }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <a class="login-brand" href="login.php">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none"><path d="M4 13.5 16 6l12 7.5v13H4v-13Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="M11 26.5v-9h10v9M4.5 13.5h23" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="M13.5 10.5h5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
            </span>
            <span class="brand-name">Store <span>Automeation</span><small>INDUSTRIAL SUPPLY</small></span>
        </a>

        <div class="login-header">
            <span class="login-security-tag">🔒 INTERNAL ACCESS ONLY</span>
            <h1>เข้าสู่ระบบคลังสินค้า</h1>
            <p>กรุณากรอกชื่อผู้ใช้และรหัสผ่านเพื่อเข้าใช้งานระบบ</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="login-error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form class="login-form" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <label>
                ชื่อผู้ใช้งาน (Username)
                <input type="text" name="username" placeholder="ระบุ username..." required autofocus value="<?= h((string) ($_POST['username'] ?? '')) ?>">
            </label>

            <label>
                รหัสผ่าน (Password)
                <input type="password" name="password" placeholder="ระบุรหัสผ่าน..." required>
            </label>

            <button class="login-submit" type="submit">เข้าสู่ระบบ</button>
        </form>

        <div class="login-hint">
            <div>🔑 บัญชีเริ่มต้น: <code>admin</code> / รหัสผ่าน: <code>admin1234</code></div>
            <div style="margin-top:4px;opacity:0.85;font-size:10px;">(เฉพาะเจ้าหน้าที่ภายใน กรุณาเปลี่ยนรหัสผ่านหลังเข้าสู่ระบบ)</div>
        </div>
    </div>
</div>
</body>
</html>

