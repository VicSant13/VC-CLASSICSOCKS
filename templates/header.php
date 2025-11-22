<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Classic Socks - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
  </head>
  <body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
      <a class="navbar-brand" href="/">
        <img src="/assets/img/logo.png" alt="Classic Socks" height="40" class="d-inline-block align-text-top">
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <?php $role = $_SESSION['role'] ?? ''; ?>
          
          <?php if ($role === 'admin'): ?>
          <li class="nav-item"><a class="nav-link" href="/admin.php?entity=categories">Categories</a></li>
          <li class="nav-item"><a class="nav-link" href="/admin.php?entity=products">Products</a></li>
          <li class="nav-item"><a class="nav-link" href="/admin.php?entity=price_list">Price List</a></li>
          <li class="nav-item"><a class="nav-link" href="/admin.php?entity=clients">Clients</a></li>
          <li class="nav-item"><a class="nav-link" href="/admin.php?entity=client_routes">Client Routes</a></li>
          <li class="nav-item"><a class="nav-link" href="/admin.php?entity=orders">Orders</a></li>
          <li class="nav-item"><a class="nav-link" href="/admin.php?entity=statistics">Statistics</a></li>
          <?php endif; ?>

          <li class="nav-item"><a class="nav-link" href="/admin.php?entity=sales">Sales</a></li>
          <li class="nav-item"><a class="nav-link" href="/admin.php?entity=sales_visual">Visual Sales</a></li>
          <li class="nav-item"><a class="nav-link" href="/admin.php?entity=maps">Maps</a></li>
        </ul>
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link btn btn-outline-light text-white ms-2" href="/logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>
  <div class="container">
