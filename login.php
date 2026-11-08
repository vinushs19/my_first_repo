<?php
require_once __DIR__ . '/includes/init.php';
//NNNN
// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
$info = '';

if (isset($_GET['timeout'])) {
    $info = 'Your session has expired due to inactivity. Please log in again.';
} elseif (isset($_GET['logged_out'])) {
    $info = 'You have successfully logged out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'] ?? 'admin';
            $_SESSION['last_activity'] = time();

            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Pharmacy Management System</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 1rem;
    }
    .login-card {
      width: 100%;
      max-width: 420px;
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
      overflow: hidden;
    }
    .login-header {
      background: #0284c7;
      color: #fff;
      padding: 2.25rem 2rem;
      text-align: center;
    }
    .login-header .icon {
      font-size: 2.5rem;
      margin-bottom: 0.5rem;
    }
    .login-header h2 {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }
    .login-header p {
      font-size: 0.85rem;
      opacity: 0.9;
    }
    .login-body {
      padding: 2rem;
    }
    .hint-box {
      margin-top: 1.5rem;
      padding: 0.75rem 1rem;
      background: #f8fafc;
      border: 1px dashed #cbd5e1;
      border-radius: 6px;
      font-size: 0.8rem;
      color: #64748b;
    }
  </style>
</head>
<body>

<div class="login-card">
  <div class="login-header">
    <div class="icon"><i class="fa-solid fa-hospital-user"></i></div>
    <h2>MediCare Pharmacy</h2>
    <p>Sign in to access Pharmacy Management System</p>
  </div>

  <div class="login-body">
    <?php if ($error): ?>
      <div class="alert alert-danger">
        <div><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($info): ?>
      <div class="alert alert-info">
        <div><i class="fa-solid fa-circle-info"></i> <?= e($info) ?></div>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrf_field() ?>

      <div class="form-group">
        <label class="form-label" for="username"><i class="fa-solid fa-user"></i> Username</label>
        <input type="text" id="username" name="username" class="form-control" placeholder="e.g. admin" required autofocus value="<?= isset($_POST['username']) ? e($_POST['username']) : '' ?>">
      </div>

      <div class="form-group">
        <label class="form-label" for="password"><i class="fa-solid fa-lock"></i> Password</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 1rem; margin-top: 0.5rem;">
        <i class="fa-solid fa-right-to-bracket"></i> Sign In
      </button>
    </form>

    <div class="hint-box">
      <strong>Default Admin Credentials:</strong><br>
      Username: <code>admin</code> | Password: <code>admin123</code>
    </div>
  </div>
</div>

</body>
</html>
