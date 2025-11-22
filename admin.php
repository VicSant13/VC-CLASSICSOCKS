<?php
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/db.php';

// Use output buffering so we can safely redirect (header()) even after templates
// have started sending HTML. This avoids "Cannot modify header information" warnings.
if (!ob_get_level()) ob_start();
$entities = [
    'categories' => [
        'table' => 'categories',
        'labels' => ['ID','Name','Active','Created At','Updated At'],
        'fields' => ['name'=>'text','active'=>'checkbox']
    ],
    'products' => [
        'table' => 'products',
        'labels' => ['ID','Name','Category','Active','Created At','Updated At'],
        'fields' => ['name'=>'text','category_id'=>'select','active'=>'checkbox']
    ],
    'price_list' => [
        'table' => 'price_list',
        'labels' => ['ID','Date','Product','Buy Price','General Price','Special Price','Active'],
        'fields' => ['date'=>'date','product_id'=>'select','buy_price'=>'number','general_price'=>'number','special_price'=>'number','active'=>'checkbox']
    ],
    'clients' => [
        'table' => 'clients',
        'labels' => ['ID','Name','Alias','Adress','Lat','Lng','Observations','Active'],
        'fields' => ['name'=>'text','alias'=>'text','adress'=>'textarea','lat'=>'text','lng'=>'text','observations'=>'textarea','active'=>'checkbox']
    ],
    'client_routes' => [
        'table' => 'client_routes',
        // include Alias and Adress in labels so headers match the rendered columns
        'labels' => ['ID','Client','Alias','Adress','Day','Visit Order','Active'],
        'fields' => ['client_id'=>'select','day'=>'text','visit_order'=>'number','active'=>'checkbox']
    ],
    'sales' => [
        'table' => 'sales',
        'labels' => ['ID','Client Route','Client','Product','Quantity','Price','Observations','Date'],
        'fields' => ['client_routes_id'=>'select','client'=>'text','product_id'=>'select','quantity'=>'number','price'=>'number','observations'=>'textarea','date'=>'date']
    ],
    'orders' => [
        'table' => 'orders',
        'labels' => ['ID','Product','Product Name','Price List','Buy Price','Quantity','Buy Date','Reception Date','Everything OK','Observations'],
        'fields' => ['product_id'=>'select','product_name'=>'text','price_list_id'=>'select','buy_price'=>'number','quantity'=>'number','buy_date'=>'date','reception_date'=>'date','everything_is_ok'=>'checkbox','observations'=>'textarea']
    ],
    // virtual section for interactive maps
    'maps' => [
        'table' => '',
        'labels' => [],
        'fields' => []
    ],
    'sales_visual' => [
        'table' => '',
        'labels' => [],
        'fields' => []
    ],
    'statistics' => [
        'table' => '',
        'labels' => [],
        'fields' => []
    ],
];

$entity = $_GET['entity'] ?? 'categories';
if (!isset($entities[$entity])) {
    echo "Entidad desconocida";
    exit;
}

$conf = $entities[$entity];
$table = $conf['table'];

// Access Control
$role = $_SESSION['role'] ?? 'salesman'; // Default to restricted if not set (though auth.php ensures logged_in)
$allowed_salesman = ['sales', 'sales_visual', 'maps'];

if ($role === 'salesman' && !in_array($entity, $allowed_salesman)) {
    echo "<div class='alert alert-danger m-4'><h1>Acceso Denegado</h1><p>No tienes permisos para acceder a esta sección.</p><a href='?entity=sales_visual' class='btn btn-primary'>Ir a Visual Sales</a></div>";
    require __DIR__ . '/templates/footer.php';
    exit;
}

// Handle actions: list, create, edit, delete
$action = $_GET['action'] ?? 'list';

// AJAX Handler for saving sales
// AJAX Handler for saving sales
if ($action === 'save_ajax' && $entity === 'sales_visual') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    
    $clientId = $input['client_id'] ?? null;
    $routeId = $input['route_id'] ?? null;
    $productId = $input['product_id'] ?? null;
    $qty = $input['quantity'] ?? 0;
    $price = $input['price'] ?? 0;
    $date = $input['date'] ?? date('Y-m-d');
    
    if (!$clientId || !$routeId || !$productId) {
        echo json_encode(['success'=>false, 'message'=>'Missing ID']);
        exit;
    }

    // Check if sale exists for this route/date/product
    $stmt = $pdo->prepare("SELECT id FROM sales WHERE client_routes_id = ? AND date = ? AND product_id = ? LIMIT 1");
    $stmt->execute([$routeId, $date, $productId]);
    $existing = $stmt->fetch();

    if ($qty > 0) {
        if ($existing) {
            $upd = $pdo->prepare("UPDATE sales SET quantity = ?, price = ? WHERE id = ?");
            $upd->execute([$qty, $price, $existing['id']]);
        } else {
            // Need client name for the 'client' text field (legacy)
            $cStmt = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
            $cStmt->execute([$clientId]);
            $cName = $cStmt->fetchColumn() ?: 'Unknown';

            $ins = $pdo->prepare("INSERT INTO sales (client_routes_id, client, product_id, quantity, price, date, observations) VALUES (?, ?, ?, ?, ?, ?, '')");
            $ins->execute([$routeId, $cName, $productId, $qty, $price, $date]);
        }
    } else {
        // If qty is 0, delete the sale
        if ($existing) {
             $del = $pdo->prepare("DELETE FROM sales WHERE id = ?");
             $del->execute([$existing['id']]);
        }
    }

    echo json_encode(['success'=>true]);
    exit;
}

require __DIR__ . '/templates/header.php';

if ($action === 'list') {
    echo "<div class=\"d-flex justify-content-between align-items-center mb-3\">";
    echo "<h2>" . ucfirst($entity) . "</h2>";
    if (!in_array($entity, ['sales_visual', 'maps', 'statistics'])) {
        echo "<a class=\"btn btn-success\" href=\"?entity={$entity}&action=create\">Nuevo</a>";
    }
    echo "</div>";

    // For products we want to show the category name instead of the raw category_id
    if ($entity === 'products') {
        $stmt = $pdo->query("SELECT p.id, p.name, p.category_id, p.active, p.created_at, p.updated_at, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT 1000");
        $rows = $stmt->fetchAll();
    }
    // For price_list show product name instead of product_id
    elseif ($entity === 'price_list') {
        $stmt = $pdo->query("SELECT pl.id, pl.date, pl.product_id, pl.buy_price, pl.general_price, pl.special_price, pl.active, pl.created_at, pl.updated_at, pr.name AS product_name FROM price_list pl LEFT JOIN products pr ON pl.product_id = pr.id ORDER BY pl.id DESC LIMIT 1000");
        $rows = $stmt->fetchAll();
    }
    // For client_routes show client name instead of client_id
    elseif ($entity === 'client_routes') {
        // include client alias and adress from clients table
        // Order by day in weekday order and then by visit_order
        $stmt = $pdo->query("SELECT cr.id, cr.client_id, cr.day, cr.visit_order, cr.active, cr.created_at, cr.updated_at, c.name AS client_name, c.alias AS client_alias, c.adress AS client_adress FROM client_routes cr LEFT JOIN clients c ON cr.client_id = c.id ORDER BY FIELD(cr.day,'LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'), cr.visit_order ASC LIMIT 1000");
        $rows = $stmt->fetchAll();
    }
    // Maps view: show clients on a map (clients with lat/lng)
    elseif ($entity === 'maps') {
    // Select clients that have lat and lng values, and collect their visit_order(s) and visit days
    $stmt = $pdo->query("SELECT c.id, c.name, c.alias, c.lat, c.lng, GROUP_CONCAT(DISTINCT cr.visit_order ORDER BY cr.visit_order SEPARATOR ',') AS visit_orders, GROUP_CONCAT(DISTINCT cr.day ORDER BY FIELD(cr.day,'LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO') SEPARATOR ',') AS visit_days FROM clients c LEFT JOIN client_routes cr ON cr.client_id = c.id WHERE c.lat IS NOT NULL AND c.lat <> '' AND c.lng IS NOT NULL AND c.lng <> '' AND c.active = 1 GROUP BY c.id ORDER BY c.name ASC");
        $clients = $stmt->fetchAll();

        // Render map container and scripts (Leaflet)        
    // Include Leaflet CSS and provide map container with a legend column on the right
    echo "<link rel=\"stylesheet\" href=\"https://unpkg.com/leaflet@1.9.4/dist/leaflet.css\">";
    echo "<div class=\"row\">";
    echo "  <div class=\"col-12 order-2\">";
    echo "    <div id=\"map\" style=\"height:80vh;border:1px solid #ddd;\"></div>";
    echo "  </div>";
    echo "  <div class=\"col-12 order-1 mb-2\">";
    // Legend box explaining colors per weekday
    echo "    <div class=\"card\"><div class=\"card-body\">";
    echo "      <h5 class=\"card-title\">Leyenda</h5>";
    echo "      <ul class=\"list-unstyled mb-0 d-flex flex-wrap gap-3\">";
    echo "        <li><span style=\"display:inline-block;width:18px;height:18px;background:#2A93EE;margin-right:8px;border-radius:3px;\"></span> LUNES</li>";
    echo "        <li><span style=\"display:inline-block;width:18px;height:18px;background:#F1C40F;margin-right:8px;border-radius:3px;\"></span> MARTES</li>";
    echo "        <li><span style=\"display:inline-block;width:18px;height:18px;background:#27AE60;margin-right:8px;border-radius:3px;\"></span> MIERCOLES</li>";
    echo "        <li><span style=\"display:inline-block;width:18px;height:18px;background:#A0522D;margin-right:8px;border-radius:3px;\"></span> JUEVES</li>";
    echo "        <li><span style=\"display:inline-block;width:18px;height:18px;background:#FF69B4;margin-right:8px;border-radius:3px;\"></span> VIERNES</li>";
    echo "        <li><span style=\"display:inline-block;width:18px;height:18px;background:#000000;margin-right:8px;border-radius:3px;\"></span> SABADO</li>";
    echo "      </ul>";
    echo "    </div></div>";
    echo "  </div>";
    echo "</div>";
    // Pass clients to JS
    $jsonClients = json_encode($clients, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    // Load Leaflet script and initialize map after it's loaded. Remove integrity to avoid load failures
    echo "<script src=\"https://unpkg.com/leaflet@1.9.4/dist/leaflet.js\"></script>";
    echo "<script>\n";
    echo "  (function(){\n";
    echo "    const clients = {$jsonClients};\n";
    echo "    function initMap(){\n";
    echo "      if (typeof L === 'undefined') {\n";
    echo "        const el = document.getElementById('map');\n";
    echo "        if (el) el.innerHTML = '<div class=\\'alert alert-danger\\'>Leaflet failed to load. Check network or remove integrity attributes.</div>';\n";
    echo "        console.error('Leaflet (L) is not available');\n";
    echo "        return;\n";
    echo "      }\n";
    echo "      const map = L.map('map');\n";
    echo "      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);\n";
    echo "      const bounds = [];\n";
    echo "      clients.forEach(function(c){\n";
    echo "        try {\n";
    echo "          const lat = parseFloat(c.lat);\n";
    echo "          const lng = parseFloat(c.lng);\n";
    echo "          if (isNaN(lat) || isNaN(lng)) return;\n";
    echo "          let orderLabel = '';\n";
    echo "          if (c.visit_orders) orderLabel = c.visit_orders.split(',')[0];\n";
    echo "          // Determine primary day for coloring (priority LUNES..SABADO)\n";
    echo "          let dayChosen = null;\n";
    echo "          if (c.visit_days) {\n";
    echo "            const days = c.visit_days.split(',');\n";
    echo "            const priority = ['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'];\n";
    echo "            for (let i=0;i<priority.length;i++){ if (days.indexOf(priority[i]) !== -1) { dayChosen = priority[i]; break; } }\n";
    echo "          }\n";
    echo "          const dayColor = { LUNES: '#2A93EE', MARTES: '#F1C40F', MIERCOLES: '#27AE60', JUEVES: '#A0522D', VIERNES: '#FF69B4', SABADO: '#000000' };\n";
    echo "          const textColor = (dayChosen === 'MARTES' || dayChosen === 'VIERNES') ? '#222' : '#fff';\n";
    echo "          const bg = dayChosen ? (dayColor[dayChosen] || '#2A93EE') : '#2A93EE';\n";
    echo "          let iconHtml = '';\n";
    echo "          if (orderLabel) {\n";
    echo "            iconHtml = '<div style=\\'background:' + bg + ';color:' + textColor + ';border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;font-weight:700;\\'>' + orderLabel + '</div>';\n";
    echo "          } else {\n";
    echo "            iconHtml = '<div style=\\'background:' + bg + ';border-radius:4px;padding:6px 8px;color:' + textColor + ';font-weight:700;\\'>&bull;</div>';\n";
    echo "          }\n";
    echo "          const icon = L.divIcon({ className: '', html: iconHtml, iconSize: [30,30], iconAnchor: [15,30] });\n";
    echo "          const marker = L.marker([lat,lng], {icon: icon}).addTo(map);\n";
    echo "          let popup = '<strong>' + (c.name || c.alias || 'Cliente') + '</strong>';\n";
    echo "          if (c.visit_orders) popup += '<br><small>Orden(es): ' + c.visit_orders + '</small>';\n";
    echo "          marker.bindPopup(popup);\n";
    echo "          bounds.push([lat,lng]);\n";
    echo "        } catch(e) { console.error(e); }\n";
    echo "      });\n";
    echo "      if (bounds.length) { map.fitBounds(bounds, {padding:[40,40]}); } else { map.setView([0,0],2); }\n";
    echo "    }\n";
    // If script loaded and DOM ready, init. Use load event to ensure leaflet.js is parsed
    echo "    if (document.readyState === 'complete' || document.readyState === 'interactive') {\n";
    echo "      // Defer a tick to ensure leaflet.js is available\n";
    echo "      setTimeout(initMap, 0);\n";
    echo "    } else {\n";
    echo "      document.addEventListener('DOMContentLoaded', function(){ setTimeout(initMap, 0); });\n";
    echo "    }\n";
    echo "  })();\n";
    echo "</script>";

        // no further list rendering
        require __DIR__ . '/templates/footer.php';
        // flush and exit to avoid rendering the standard table below
        if (ob_get_level()) ob_end_flush();
        exit;
    }
    // For sales allow filtering by date range and compute totals
    elseif ($entity === 'sales') {
        // read filter params from GET (use GET so form can submit)
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';

        $where = [];
        $params_q = [];
        if ($from) {
            $where[] = 'date >= ?';
            $params_q[] = $from;
        }
        if ($to) {
            $where[] = 'date <= ?';
            $params_q[] = $to;
        }

        $where_sql = '';
        if ($where) $where_sql = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT * FROM sales {$where_sql} ORDER BY date DESC, id DESC LIMIT 1000";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params_q);
        $rows = $stmt->fetchAll();
    } elseif ($entity === 'sales_visual') {
        // Visual Sales View
        $days = ['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'];
        $mapDays = [
            'Mon' => 'LUNES', 'Tue' => 'MARTES', 'Wed' => 'MIERCOLES',
            'Thu' => 'JUEVES', 'Fri' => 'VIERNES', 'Sat' => 'SABADO', 'Sun' => 'LUNES'
        ];
        $todayEng = date('D');
        $defaultDay = $mapDays[$todayEng] ?? 'LUNES';
        
        $selectedDate = $_GET['date'] ?? date('Y-m-d');
        
        // Calculate Day from Date
        $timestamp = strtotime($selectedDate);
        $dayEng = date('D', $timestamp);
        $selectedDay = $mapDays[$dayEng] ?? 'LUNES';

        // Role check
        $role = $_SESSION['role'] ?? 'salesman';
        $dateReadonly = ($role === 'salesman') ? 'readonly' : '';
        $dayDisabled = 'disabled'; // Always disabled as it is auto-calculated

        // Fetch active products
        $products = $pdo->query("SELECT id, name FROM products WHERE active = 1 ORDER BY name ASC")->fetchAll();

        // Filter form
        echo "<form class=\"row g-3 mb-4 align-items-end\">";
        echo "<input type=\"hidden\" name=\"entity\" value=\"sales_visual\">";
        // We need to pass the day as hidden if disabled, but actually we calculate it on server side anyway.
        // But for UI consistency let's keep the select disabled.
        echo "<div class=\"col-auto\">";
        echo "<label class=\"form-label\">Día de Ruta</label>";
        echo "<select name=\"day_display\" class=\"form-select\" disabled>";
        foreach ($days as $d) {
            $sel = ($d === $selectedDay) ? 'selected' : '';
            echo "<option value=\"{$d}\" {$sel}>{$d}</option>";
        }
        echo "</select>";
        // Hidden input for day is not strictly needed if we calculate it from date, but let's be safe? 
        // Actually we calculate $selectedDay from $selectedDate at the top, so we don't need to submit 'day'.
        echo "</div>";
        echo "<div class=\"col-auto\">";
        echo "<label class=\"form-label\">Fecha de Venta</label>";
        echo "<input type=\"date\" name=\"date\" class=\"form-control\" value=\"{$selectedDate}\" onchange=\"this.form.submit()\" {$dateReadonly}>";
        echo "</div>";
        echo "</form>";

        // Get routes and sales
        $sql = "SELECT cr.id as route_id, cr.client_id, cr.visit_order, c.name as client_name, 
                       s.quantity, s.price, s.id as sale_id, s.product_id, p.name as product_name
                FROM client_routes cr 
                JOIN clients c ON cr.client_id = c.id 
                LEFT JOIN sales s ON s.client_routes_id = cr.id AND s.date = ?
                LEFT JOIN products p ON s.product_id = p.id
                WHERE cr.day = ? AND cr.active = 1
                ORDER BY cr.visit_order ASC, s.id ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$selectedDate, $selectedDay]);
        $rows = $stmt->fetchAll();

        // Group by route
        $routes = [];
        foreach ($rows as $r) {
            $rid = $r['route_id'];
            if (!isset($routes[$rid])) {
                $routes[$rid] = [
                    'route_id' => $rid,
                    'client_id' => $r['client_id'],
                    'client_name' => $r['client_name'],
                    'visit_order' => $r['visit_order'],
                    'sales' => []
                ];
            }
            if ($r['sale_id']) {
                $routes[$rid]['sales'][] = $r;
            }
        }

        echo "<div class=\"row\">"; // Main container for cards

        $grandTotal = 0;
        $grandTotalQty = 0;

        foreach ($routes as $r) {
            echo "<div class=\"col-12 col-lg-6 mb-4\" data-route-id=\"{$r['route_id']}\" data-client-id=\"{$r['client_id']}\">";
            echo "<div class=\"card shadow-sm h-100\">";
            
            // Card Header
            $badgeClass = !empty($r['sales']) ? 'bg-success' : 'bg-secondary';
            echo "<div class=\"card-header d-flex justify-content-between align-items-center bg-light\">";
            echo "<h5 class=\"mb-0 text-truncate\" title=\"" . e($r['client_name']) . "\">" . e($r['client_name']) . "</h5>";
            echo "<span class=\"badge {$badgeClass}\">Order: " . e($r['visit_order']) . "</span>";
            echo "</div>";

            // Card Body: Existing Sales
            echo "<div class=\"card-body p-0\">";
            if (empty($r['sales'])) {
                echo "<div class=\"p-3 text-muted text-center small\">No sales yet</div>";
            } else {
                echo "<ul class=\"list-group list-group-flush\">";
                foreach ($r['sales'] as $sale) {
                    $total = ((float)$sale['quantity']) * ((float)$sale['price']);
                    $grandTotal += $total;
                    $grandTotalQty += (float)$sale['quantity'];

                    echo "<li class=\"list-group-item\">";
                    echo "<div class=\"d-flex justify-content-between align-items-start\">";
                    echo "<div>";
                    echo "<div class=\"fw-bold\">" . e($sale['product_name'] ?: 'Unknown') . "</div>";
                    echo "<small class=\"text-muted\">" . e($sale['quantity']) . " x $" . number_format($sale['price'], 2) . "</small>";
                    echo "</div>";
                    echo "<div class=\"text-end\">";
                    echo "<div class=\"fw-bold text-success\">$" . number_format($total, 2) . "</div>";
                    echo "<button class=\"btn btn-sm btn-link text-danger p-0 delete-btn\" data-sale-id=\"{$sale['sale_id']}\" data-product-id=\"{$sale['product_id']}\" style=\"text-decoration:none\">&times; Remove</button>";
                    echo "</div>";
                    echo "</div>";
                    echo "</li>";
                }
                echo "</ul>";
            }
            echo "</div>";

            // Card Footer: Add New Sale Form
            echo "<div class=\"card-footer bg-white\">";
            echo "<div class=\"row g-2 align-items-end\">";
            
            // Product Select
            echo "<div class=\"col-12\">";
            echo "<label class=\"form-label small text-muted mb-1\">Product</label>";
            echo "<select class=\"form-select form-select-sm product-select\">";
            echo "<option value=\"\">Select Product...</option>";
            foreach ($products as $p) {
                echo "<option value=\"{$p['id']}\">" . e($p['name']) . "</option>";
            }
            echo "</select>";
            echo "</div>";
            
            // Qty
            echo "<div class=\"col-4\">";
            echo "<label class=\"form-label small text-muted mb-1\">Qty</label>";
            echo "<input type=\"number\" class=\"form-control form-control-sm qty-input\" placeholder=\"0\">";
            echo "</div>";
            
            // Price
            echo "<div class=\"col-4\">";
            echo "<label class=\"form-label small text-muted mb-1\">Price</label>";
            echo "<input type=\"number\" class=\"form-control form-control-sm price-input\" placeholder=\"0.00\">";
            echo "</div>";
            
            // Add Button
            echo "<div class=\"col-4\">";
            echo "<button class=\"btn btn-sm btn-primary w-100 save-btn\">Add</button>";
            echo "</div>";
            
            echo "</div>"; // end row
            echo "</div>"; // end card-footer

            echo "</div>"; // end card
            echo "</div>"; // end col
        }

        echo "</div>"; // end row

        // Sticky Footer for Totals
        echo "<div class=\"fixed-bottom bg-dark text-white p-2 shadow-lg\" style=\"z-index: 1030;\">";
        echo "<div class=\"container d-flex justify-content-between align-items-center\">";
        echo "<span class=\"fs-5\">Total:</span>";
        echo "<div>";
        echo "<span class=\"badge bg-primary fs-6 me-2\">" . number_format($grandTotalQty) . " pzs</span>";
        echo "<span class=\"badge bg-success fs-6\">$" . number_format($grandTotal, 2) . "</span>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
        // Add padding to body to prevent content being hidden behind fixed footer
        echo "<div style=\"height: 60px;\"></div>";

        // JavaScript
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            const date = '{$selectedDate}';
            
            document.querySelectorAll('.save-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const card = this.closest('.col-12'); // The column wrapper has the data attributes
                    const routeId = card.dataset.routeId;
                    const clientId = card.dataset.clientId;
                    
                    const footer = this.closest('.card-footer');
                    const productSelect = footer.querySelector('.product-select');
                    const productId = productSelect.value;
                    const qty = footer.querySelector('.qty-input').value;
                    const price = footer.querySelector('.price-input').value;
                    
                    if (!productId || !qty) {
                        alert('Please select a product and quantity');
                        return;
                    }

                    const btn = this;
                    btn.disabled = true;
                    btn.textContent = '...';
                    
                    fetch('?entity=sales_visual&action=save_ajax', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': '{$_SESSION['csrf_token']}'
                        },
                        body: JSON.stringify({
                            csrf_token: '{$_SESSION['csrf_token']}',
                            route_id: routeId,
                            client_id: clientId,
                            product_id: productId,
                            quantity: qty,
                            price: price,
                            date: date
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            location.reload(); 
                        } else {
                            alert('Error: ' + (data.message || 'Unknown'));
                            btn.disabled = false;
                            btn.textContent = 'Add';
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        btn.disabled = false;
                        btn.textContent = 'Add';
                    });
                });
            });

            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if(!confirm('Delete this sale?')) return;
                    
                    const card = this.closest('.col-12');
                    const routeId = card.dataset.routeId;
                    const clientId = card.dataset.clientId;
                    const productId = this.dataset.productId;
                    
                    fetch('?entity=sales_visual&action=save_ajax', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': '{$_SESSION['csrf_token']}'
                        },
                        body: JSON.stringify({
                            csrf_token: '{$_SESSION['csrf_token']}',
                            route_id: routeId,
                            client_id: clientId,
                            product_id: productId,
                            quantity: 0, // Delete signal
                            price: 0,
                            date: date
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            location.reload();
                        } else {
                            alert('Error deleting');
                        }
                    })
                    .catch(err => console.error(err));
                });
            });
        });
        </script>";
        
        require __DIR__ . '/templates/footer.php';
        if (ob_get_level()) ob_end_flush();
        exit;

    } elseif ($entity === 'statistics') {
        // Statistics View
        // Query weekly sales (Monday as start of week -> mode 1)
        $sql = "SELECT YEARWEEK(date, 1) as week_code, SUM(quantity * price) as total FROM sales GROUP BY week_code ORDER BY week_code ASC";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll();

        // Top 10 Clients
        $sqlTop = "SELECT client, SUM(quantity * price) as total FROM sales GROUP BY client ORDER BY total DESC LIMIT 10";
        $stmtTop = $pdo->query($sqlTop);
        $topClients = $stmtTop->fetchAll();

        // Bottom 10 Clients
        $sqlBottom = "SELECT client, SUM(quantity * price) as total FROM sales GROUP BY client ORDER BY total ASC LIMIT 10";
        $stmtBottom = $pdo->query($sqlBottom);
        $bottomClients = $stmtBottom->fetchAll();

        $labels = [];
        $values = [];
        foreach ($data as $d) {
            // week_code is like 202345 (YearWeek)
            $wc = $d['week_code'];
            $year = substr($wc, 0, 4);
            $week = substr($wc, 4);
            $labels[] = "Semana $week - $year";
            $values[] = (float)$d['total'];
        }

        echo "<h2>Estadísticas de Ventas</h2>";
        
        // Heatmap Data
        $sqlHeat = "SELECT c.lat, c.lng, SUM(s.quantity * s.price) as total_sales
                    FROM sales s
                    JOIN client_routes cr ON s.client_routes_id = cr.id
                    JOIN clients c ON cr.client_id = c.id
                    WHERE c.lat IS NOT NULL AND c.lng IS NOT NULL AND c.lat <> '' AND c.lng <> ''
                    GROUP BY c.id";
        $stmtHeat = $pdo->query($sqlHeat);
        $heatData = $stmtHeat->fetchAll();
        
        $heatPoints = [];
        $maxVal = 0;
        foreach ($heatData as $h) {
            $val = (float)$h['total_sales'];
            if ($val > $maxVal) $maxVal = $val;
            $heatPoints[] = [(float)$h['lat'], (float)$h['lng'], $val];
        }
        $jsonHeat = json_encode($heatPoints);
        $maxVal = $maxVal ?: 1; // Avoid div by zero

        echo "<div class=\"card shadow-sm mb-4\">";
        echo "<div class=\"card-header bg-white\"><h5>Mapa de Calor de Ventas</h5></div>";
        echo "<div class=\"card-body p-0\">";
        echo "<div id=\"heatMap\" style=\"height: 500px;\"></div>";
        echo "</div></div>";

        echo "<link rel=\"stylesheet\" href=\"https://unpkg.com/leaflet@1.9.4/dist/leaflet.css\">";
        echo "<script src=\"https://unpkg.com/leaflet@1.9.4/dist/leaflet.js\"></script>";
        echo "<script src=\"https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js\"></script>";
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof L === 'undefined') return;
            
            const map = L.map('heatMap').setView([0, 0], 2);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const points = {$jsonHeat};
            if (points.length > 0) {
                // Calculate bounds
                const bounds = L.latLngBounds(points.map(p => [p[0], p[1]]));
                map.fitBounds(bounds, {padding: [50, 50]});
                
                // Add heat layer
                // We can normalize intensity or use max option. 
                // Leaflet.heat expects [lat, lng, intensity]
                L.heatLayer(points, {
                    radius: 25,
                    blur: 15,
                    maxZoom: 10,
                    max: {$maxVal}, // Maximum intensity value
                    gradient: {0.4: 'blue', 0.65: 'lime', 1: 'red'}
                }).addTo(map);
            }
        });
        </script>";
        echo "<div class=\"card shadow-sm mb-4\">";
        echo "<div class=\"card-body\">";
        echo "<canvas id=\"salesChart\" style=\"max-height: 400px;\"></canvas>";
        echo "</div></div>";

        echo "<div class=\"row\">";
        
        // Top 10 Table
        echo "<div class=\"col-md-6 mb-4\">";
        echo "<div class=\"card shadow-sm\">";
        echo "<div class=\"card-header bg-success text-white\"><h5 class=\"mb-0\">Top 10 Clientes (Histórico - Más Ventas)</h5></div>";
        echo "<div class=\"table-responsive\">";
        echo "<table class=\"table table-striped mb-0\">";
        echo "<thead><tr><th>Cliente</th><th class=\"text-end\">Total</th></tr></thead>";
        echo "<tbody>";
        foreach ($topClients as $c) {
            echo "<tr>";
            echo "<td>" . e($c['client']) . "</td>";
            echo "<td class=\"text-end\">$" . number_format($c['total'], 2) . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "</div></div></div>";

        // Bottom 10 Table
        echo "<div class=\"col-md-6 mb-4\">";
        echo "<div class=\"card shadow-sm\">";
        echo "<div class=\"card-header bg-danger text-white\"><h5 class=\"mb-0\">Top 10 Clientes (Histórico - Menos Ventas)</h5></div>";
        echo "<div class=\"table-responsive\">";
        echo "<table class=\"table table-striped mb-0\">";
        echo "<thead><tr><th>Cliente</th><th class=\"text-end\">Total</th></tr></thead>";
        echo "<tbody>";
        foreach ($bottomClients as $c) {
            echo "<tr>";
            echo "<td>" . e($c['client']) . "</td>";
            echo "<td class=\"text-end\">$" . number_format($c['total'], 2) . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "</div></div></div>";
        
        echo "</div>";

        $jsonLabels = json_encode($labels);
        $jsonValues = json_encode($values);

        echo "<script src=\"https://cdn.jsdelivr.net/npm/chart.js\"></script>";
        echo "<script>
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: {$jsonLabels},
                datasets: [{
                    label: 'Ventas Mensuales ($)',
                    data: {$jsonValues},
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1,
                    fill: true,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return '$' + value; }
                        }
                    }
                }
            }
        });
        </script>";

        require __DIR__ . '/templates/footer.php';
        if (ob_get_level()) ob_end_flush();
        exit;

    } else {
        $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY id DESC LIMIT 1000");
        $rows = $stmt->fetchAll();
    }

    // If we're viewing sales, show a filter form (date range)
    $filter_from = $_GET['from'] ?? '';
    $filter_to = $_GET['to'] ?? '';
    if ($entity === 'sales') {
        echo "<form class=\"row g-2 mb-3\" method=\"get\">";
        // preserve entity and action in GET
        echo "<input type=\"hidden\" name=\"entity\" value=\"sales\">";
        echo "<input type=\"hidden\" name=\"action\" value=\"list\">";
        echo "<div class=\"col-auto\"><label class=\"form-label\">Desde</label><input class=\"form-control\" type=\"date\" name=\"from\" value=\"".e($filter_from)."\"></div>";
        echo "<div class=\"col-auto\"><label class=\"form-label\">Hasta</label><input class=\"form-control\" type=\"date\" name=\"to\" value=\"".e($filter_to)."\"></div>";
        echo "<div class=\"col-auto align-self-end\"><button class=\"btn btn-primary\" type=\"submit\">Filtrar</button> <a class=\"btn btn-secondary\" href=\"?entity=sales&action=list\">Limpiar</a></div>";
        echo "</form>";
    }

    echo "<table class=\"table table-striped\"><thead><tr>";
    foreach ($conf['labels'] as $lbl) echo "<th>".e($lbl)."</th>";
    echo "<th>Actions</th></tr></thead><tbody>";
    // initialize totals if sales
    $total_qty = 0.0;
    $total_amount = 0.0;
    foreach ($rows as $r) {
        if ($entity === 'sales') {
            // accumulate totals (cast to float)
            $total_qty += (float)($r['quantity'] ?? 0);
            $total_amount += ((float)($r['quantity'] ?? 0)) * ((float)($r['price'] ?? 0));
        }
        echo "<tr>";
        // Print columns. For products and price_list we print specific column orders and show related names.
        if ($entity === 'products') {
            $cols = ['id','name','category_id','active','created_at','updated_at'];
            foreach ($cols as $col) {
                if ($col === 'category_id') {
                    $label = isset($r['category_name']) ? $r['category_name'] : $r[$col];
                    echo "<td>".e($label)."</td>";
                } else {
                    echo "<td>".e($r[$col])."</td>";
                }
            }
        } elseif ($entity === 'price_list') {
            $cols = ['id','date','product_id','buy_price','general_price','special_price','active','created_at','updated_at'];
            foreach ($cols as $col) {
                if ($col === 'product_id') {
                    $label = isset($r['product_name']) ? $r['product_name'] : $r[$col];
                    echo "<td>".e($label)."</td>";
                } else {
                    echo "<td>".e($r[$col])."</td>";
                }
            }
        } elseif ($entity === 'client_routes') {
            // include client alias and adress columns
            $cols = ['id','client_id','client_alias','client_adress','day','visit_order','active','created_at','updated_at'];
            // We'll group visually by day: when day changes, print a separator row
            static $prev_day;
            $prev_day = $prev_day ?? null;
            // compute colspan to span the full table (labels + Actions)
            $colspan = count($conf['labels']) + 1;
            if (!isset($grouped_marker)) {
                // initialize per-request variable
                $grouped_marker = null;
            }
            // If day changed compared to previous row, print header row
            if ($grouped_marker !== ($r['day'] ?? null)) {
                $grouped_marker = $r['day'] ?? null;
                echo "<tr class=\"table-secondary\"><td colspan=\"".intval($colspan)."\"><strong>".e(strtoupper($grouped_marker))."</strong></td></tr>";
            }
            foreach ($cols as $col) {
                if ($col === 'client_id') {
                    $label = isset($r['client_name']) ? $r['client_name'] : $r[$col];
                    echo "<td>".e($label)."</td>";
                } elseif ($col === 'client_alias') {
                    echo "<td>".e($r['client_alias'] ?? '') . "</td>";
                } elseif ($col === 'client_adress') {
                    echo "<td>".e($r['client_adress'] ?? '') . "</td>";
                } else {
                    echo "<td>".e($r[$col])."</td>";
                }
            }
        } else {
            // Print common columns generically
            foreach (array_keys($r) as $col) {
                if (in_array($col,['created_at','updated_at'])) {
                    echo "<td>".e($r[$col])."</td>";
                } else {
                    echo "<td>".e($r[$col])."</td>";
                }
            }
        }
        echo "<td style=\"width:150px\">";
        echo "<a class=\"btn btn-sm btn-primary me-1\" href=\"?entity={$entity}&action=edit&id={$r['id']}\">Edit</a>";
        echo "<form method=\"post\" action=\"?entity={$entity}&action=delete&id={$r['id']}\" style=\"display:inline;\" onsubmit=\"return confirm('Seguro?');\">";
        echo "<input type=\"hidden\" name=\"csrf_token\" value=\"{$_SESSION['csrf_token']}\">";
        echo "<button type=\"submit\" class=\"btn btn-sm btn-danger\">Delete</button>";
        echo "</form>";
        echo "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";

    // If sales, show totals summary
    if ($entity === 'sales') {
        echo "<div class=\"mt-3\">";
        echo "<div class=\"alert alert-light\">";
        echo "<strong>Total quantity:</strong> " . e(number_format($total_qty, 2, '.', ',')) . " &nbsp; &nbsp; ";
        echo "<strong>Total amount:</strong> $" . e(number_format($total_amount, 2, '.', ','));
        echo "</div>";
        echo "</div>";
    }

} elseif ($action === 'create' || $action === 'edit') {
    $id = $_GET['id'] ?? null;
    $data = [];
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch() ?: [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF Check
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        // For AJAX JSON requests, we might need to read from input or headers.
        // The save_ajax uses JSON body, so we need to handle that.
        
        // Check if it's a JSON request
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            $token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
        
        verify_csrf_token($token);

        // Build SQL dynamically
        $fields = $conf['fields'];
        $params = [];
        $placeholders = [];
        $updates = [];

        foreach ($fields as $f => $type) {
            if ($type === 'checkbox') {
                $val = isset($_POST[$f]) ? 1 : 0;
            } else {
                $val = $_POST[$f] ?? null;
            }
            $params[$f] = $val;
        }

        // For sales, auto-fill the 'client' field from the selected client_routes_id
        if ($entity === 'sales') {
            $crid = $params['client_routes_id'] ?? null;
            if ($crid) {
                $q = $pdo->prepare("SELECT COALESCE(c.name, '') AS client_name FROM client_routes cr LEFT JOIN clients c ON cr.client_id = c.id WHERE cr.id = ? LIMIT 1");
                $q->execute([$crid]);
                $rowClient = $q->fetch();
                $params['client'] = $rowClient ? $rowClient['client_name'] : '';
            } else {
                // ensure client is empty if no route selected
                $params['client'] = '';
            }
        }

        // For orders, auto-fill product_name from products and buy_price from price_list
        if ($entity === 'orders') {
            // product_name <- products.name via product_id
            $prodId = $params['product_id'] ?? null;
            if ($prodId) {
                $q = $pdo->prepare("SELECT name FROM products WHERE id = ? LIMIT 1");
                $q->execute([$prodId]);
                $rprod = $q->fetch();
                $params['product_name'] = $rprod ? $rprod['name'] : '';
            } else {
                $params['product_name'] = '';
            }

            // buy_price <- price_list.buy_price via price_list_id
            $plId = $params['price_list_id'] ?? null;
            if ($plId) {
                $q2 = $pdo->prepare("SELECT buy_price FROM price_list WHERE id = ? LIMIT 1");
                $q2->execute([$plId]);
                $rpl = $q2->fetch();
                // ensure a numeric value (or empty string)
                $params['buy_price'] = $rpl ? $rpl['buy_price'] : null;
            } else {
                $params['buy_price'] = null;
            }
        }

        if ($id) {
            $set = [];
            foreach ($params as $k=>$v) { $set[] = "{$k} = :{$k}"; }
            $sql = "UPDATE {$table} SET " . implode(',', $set) . " WHERE id = :id";
            $params['id'] = $id;
        } else {
            $sql = "INSERT INTO {$table} (" . implode(',', array_keys($params)) . ") VALUES (" . implode(',', array_map(function($k){return ':' . $k;}, array_keys($params))) . ")";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        header("Location: ?entity={$entity}&action=list");
        exit;
    }

    echo "<h2>" . ($id ? "Editar" : "Crear") . " " . e($entity) . "</h2>";
    echo "<form method=\"post\">";
    echo "<input type=\"hidden\" name=\"csrf_token\" value=\"{$_SESSION['csrf_token']}\">";
    foreach ($conf['fields'] as $f => $type) {
        $val = $data[$f] ?? '';
        echo "<div class=\"mb-3\">";
        echo "<label class=\"form-label\">".e(ucfirst(str_replace('_',' ',$f)))."</label>";
        // Special case: for client_routes.day render a select with weekdays
        if ($f === 'day' && $entity === 'client_routes') {
            $days = ['LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'];
            echo "<select name=\"{$f}\" class=\"form-select\">";
            echo "<option value=\"\">--</option>";
            foreach ($days as $d) {
                $sel = ($val === $d) ? 'selected' : '';
                echo "<option value=\"".e($d)."\" {$sel}>".e($d)."</option>";
            }
            echo "</select>";
        } elseif ($entity === 'sales' && $f === 'client') {
            // For sales, the client field is auto-filled server-side; keep it invisible to the user
            $display = $data[$f] ?? '';
            // Hidden input so value is submitted (server will overwrite anyway)
            echo "<input type=\"hidden\" name=\"{$f}\" value=\"".e($display)."\">";
        } elseif ($entity === 'orders' && $f === 'product_name') {
            // product_name is auto-filled from products.name; do not allow manual edit
            $display = $data[$f] ?? '';
            echo "<input type=\"hidden\" name=\"{$f}\" value=\"".e($display)."\">";
            // Optionally show the value read-only for UX (uncomment if desired)
            // echo "<div class=\"form-text\">".e($display)."</div>";
        } elseif ($entity === 'orders' && $f === 'buy_price') {
            // buy_price is taken from selected price_list.buy_price; do not allow manual edit
            $display = $data[$f] ?? '';
            echo "<input type=\"hidden\" name=\"{$f}\" value=\"".e($display)."\">";
            // Optionally show the value read-only for UX (uncomment if desired)
            // echo "<div class=\"form-text\">Precio compra: $".e($display)."</div>";
        } elseif ($type === 'textarea') {
            echo "<textarea name=\"{$f}\" class=\"form-control\">".e($val)."</textarea>";
        } elseif ($type === 'select') {
            // Simple select population for FK fields
            // Select queries should return id and a label column named 'label'
            if ($f === 'category_id') {
                $opts = $pdo->query("SELECT id, name AS label FROM categories")->fetchAll();
            } elseif ($f === 'product_id') {
                $opts = $pdo->query("SELECT id, name AS label FROM products")->fetchAll();
            } elseif ($f === 'client_id') {
                $opts = $pdo->query("SELECT id, name AS label FROM clients")->fetchAll();
            } elseif ($f === 'client_routes_id') {
                // show client name, day and visit order as label for selection in sales
                // label in the order: DAY - VisitOrder - ClientName
                $opts = $pdo->query("SELECT cr.id, CONCAT(cr.day, ' - ', cr.visit_order, ' - ', COALESCE(c.name,'')) AS label FROM client_routes cr LEFT JOIN clients c ON cr.client_id = c.id ORDER BY FIELD(cr.day,'LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'), cr.visit_order ASC")->fetchAll();
            } elseif ($f === 'price_list_id') {
                // show product name and buy_price as label for selection in orders
                // Label format: PRODUCT_NAME - $BUY_PRICE (you can include date if desired)
                $opts = $pdo->query("SELECT pl.id, CONCAT(COALESCE(pr.name,''), ' - $', IFNULL(pl.buy_price, 0)) AS label FROM price_list pl LEFT JOIN products pr ON pl.product_id = pr.id ORDER BY pl.date DESC")->fetchAll();
            } else {
                $opts = [];
            }
            echo "<select name=\"{$f}\" class=\"form-select\">";
            echo "<option value=\"\">--</option>";
            foreach ($opts as $o) {
                $ov = $o['id'];
                // use associative 'label' column
                $ol = isset($o['label']) ? $o['label'] : (isset($o['name']) ? $o['name'] : $ov);
                $sel = ($val == $ov) ? 'selected' : '';
                echo "<option value=\"".e($ov)."\" {$sel}>".e($ol)."</option>";
            }
            echo "</select>";
        } elseif ($type === 'checkbox') {
            $checked = ($val) ? 'checked' : '';
            echo "<div class=\"form-check\"><input class=\"form-check-input\" type=\"checkbox\" name=\"{$f}\" value=\"1\" {$checked}></div>";
        } else {
            $inputType = $type === 'number' ? 'number' : ($type === 'date' ? 'date' : 'text');
            // If this is a date input in create/edit forms, mark it to get default today's date via JS
            if ($inputType === 'date') {
                echo "<input class=\"form-control\" type=\"date\" name=\"{$f}\" value=\"".e($val)."\" data-default-today>";
            } else {
                echo "<input class=\"form-control\" type=\"{$inputType}\" name=\"{$f}\" value=\"".e($val)."\">";
            }
        }
        echo "</div>";
    }
    echo "<button class=\"btn btn-primary\" type=\"submit\">Guardar</button> ";
    echo "<a class=\"btn btn-secondary\" href=\"?entity={$entity}&action=list\">Cancelar</a>";
    echo "</form>";

} elseif ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        verify_csrf_token($token);
        
        $id = $_GET['id'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
            $stmt->execute([$id]);
        }
    }
    header("Location: ?entity={$entity}&action=list");
    exit;
}

require __DIR__ . '/templates/footer.php';

// Flush output buffer if we started it here
if (ob_get_level()) {
    ob_end_flush();
}
