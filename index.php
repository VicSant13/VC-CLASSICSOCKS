<?php 
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/templates/header.php'; 
?>
<div class="text-center">
  <h1 class="mb-4">Administración - Classic Socks</h1>
  <p class="lead">Usa el menú para navegar por los catálogos. Interfaz construida con Bootstrap para mantenerla limpia.</p>
  <div class="row mt-4">
    <div class="col-md-4"><a class="btn btn-primary w-100" href="/admin.php?entity=categories">Categories</a></div>
    <div class="col-md-4"><a class="btn btn-primary w-100" href="/admin.php?entity=products">Products</a></div>
    <div class="col-md-4"><a class="btn btn-primary w-100" href="/admin.php?entity=price_list">Price List</a></div>
  </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
