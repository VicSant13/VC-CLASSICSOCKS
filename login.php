<?php
session_start();
$config = require __DIR__ . '/inc/config.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = $_POST['key'] ?? '';
    // Check for Salesman
    if ($key === 'salesman2025') {
        $_SESSION['logged_in'] = true;
        $_SESSION['role'] = 'salesman';
        header('Location: /admin.php?entity=sales_visual');
        exit;
    } 
    // Check for Admin
    elseif ($key === $config['master_key']) {
        $_SESSION['logged_in'] = true;
        $_SESSION['role'] = 'admin';
        header('Location: /admin.php');
        exit;
    } else {
        $error = 'Clave incorrecta';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Classic Socks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f5f5; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { width: 100%; max-width: 400px; padding: 2rem; background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="login-card">
        <h3 class="text-center mb-4">Acceso Restringido</h3>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Clave Maestra</label>
                <input type="password" name="key" class="form-control" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>
    </div>
</body>
</html>
