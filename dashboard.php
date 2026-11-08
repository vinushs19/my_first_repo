<?php
$page_title = 'Dashboard Overview';
require_once __DIR__ . '/includes/header.php';

// 1. Core Summary Metrics
$stmt = $pdo->query("SELECT COUNT(*) FROM medicines WHERE is_active = 1");
$total_medicines = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM medicines WHERE is_active = 1 AND quantity <= reorder_level");
$low_stock_count = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM medicines WHERE is_active = 1 AND expiry_date IS NOT NULL AND expiry_date < CURDATE()");
$expired_count = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE(sale_date) = CURDATE()");
$today_sales = (float)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM suppliers");
$total_suppliers = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM customers");
$total_customers = (int)$stmt->fetchColumn();

// 2. Fetch critical stock / expiry alerts (Limit 8)
$alert_stmt = $pdo->query("
    SELECT m.*, c.category_name 
    FROM medicines m
    LEFT JOIN categories c ON c.category_id = m.category_id
    WHERE m.is_active = 1 
      AND (m.quantity <= m.reorder_level OR (m.expiry_date IS NOT NULL AND m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)))
    ORDER BY (m.expiry_date < CURDATE()) DESC, m.quantity ASC
    LIMIT 8
");
$alert_items = $alert_stmt->fetchAll();

// 3. Fetch recent sales (Limit 8)
$sales_stmt = $pdo->query("
    SELECT s.*, c.name AS customer_name 
    FROM sales s
    LEFT JOIN customers c ON c.customer_id = s.customer_id
    ORDER BY s.sale_date DESC
    LIMIT 8
");
$recent_sales = $sales_stmt->fetchAll();

// 4. Data for 7-Day Chart: Sales vs Purchases
$past_days = [];
for ($i = 6; $i >= 0; $i--) {
    $past_days[] = date('Y-m-d', strtotime("-$i days"));
}

$chart_labels = [];
$daily_sales_data = [];
$daily_purchases_data = [];

// Prepare sales map
$stmt = $pdo->query("
    SELECT DATE(sale_date) as d, COALESCE(SUM(total_amount), 0) as amt 
    FROM sales 
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(sale_date)
");
$sales_map = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Prepare purchases map
$stmt = $pdo->query("
    SELECT purchase_date as d, COALESCE(SUM(total_amount), 0) as amt 
    FROM purchases 
    WHERE purchase_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY purchase_date
");
$purchases_map = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

foreach ($past_days as $day) {
    $chart_labels[] = date('M d', strtotime($day));
    $daily_sales_data[] = (float)($sales_map[$day] ?? 0);
    $daily_purchases_data[] = (float)($purchases_map[$day] ?? 0);
}
?>

<!-- Metric Cards -->
<div class="stats-grid">
  <div class="stat-card green">
    <div class="stat-info">
      <h4>Today's Sales</h4>
      <div class="stat-value"><?= format_currency($today_sales) ?></div>
    </div>
    <div class="stat-icon"><i class="fa-solid fa-cash-register"></i></div>
  </div>

  <div class="stat-card blue">
    <div class="stat-info">
      <h4>Active Medicines</h4>
      <div class="stat-value"><?= $total_medicines ?></div>
    </div>
    <div class="stat-icon"><i class="fa-solid fa-pills"></i></div>
  </div>

  <div class="stat-card orange">
    <div class="stat-info">
      <h4>Low Stock Items</h4>
      <div class="stat-value"><?= $low_stock_count ?></div>
    </div>
    <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
  </div>

  <div class="stat-card red">
    <div class="stat-info">
      <h4>Expired Medicines</h4>
      <div class="stat-value"><?= $expired_count ?></div>
    </div>
    <div class="stat-icon"><i class="fa-solid fa-calendar-xmark"></i></div>
  </div>
</div>

<!-- 7-Day Chart -->
<div class="card">
  <div class="card-header">
    <h3><i class="fa-solid fa-chart-line" style="color:var(--primary);margin-right:0.5rem;"></i> Financial Activity (Last 7 Days)</h3>
    <span style="font-size:0.8rem;color:var(--text-muted);">Sales Revenue vs Purchase Expenditures</span>
  </div>
  <div class="card-body">
    <div style="height: 280px; position: relative;">
      <canvas id="financialChart"></canvas>
    </div>
  </div>
</div>

<!-- Side-by-Side Tables -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.5rem;">
  
  <!-- Critical Alerts Card -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-bell" style="color:var(--warning);margin-right:0.5rem;"></i> Stock & Expiry Alerts</h3>
      <a href="<?= BASE_URL ?>/medicines/index.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="card-body" style="padding: 0;">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Medicine</th>
              <th>Stock</th>
              <th>Expiry</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($alert_items)): ?>
              <tr>
                <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                  <i class="fa-solid fa-circle-check" style="color: var(--success); font-size: 1.5rem; display:block; margin-bottom: 0.5rem;"></i>
                  All medicine stocks and expiry dates are in good health.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($alert_items as $item): 
                $is_expired = $item['expiry_date'] && $item['expiry_date'] < date('Y-m-d');
                $is_near = $item['expiry_date'] && !$is_expired && (strtotime($item['expiry_date']) - time() <= 30 * 86400);
                $is_low = $item['quantity'] <= $item['reorder_level'];
              ?>
                <tr>
                  <td>
                    <strong><?= e($item['name']) ?></strong>
                    <div style="font-size:0.75rem;color:var(--text-muted);">Batch: <?= e($item['batch_no'] ?: 'N/A') ?></div>
                  </td>
                  <td>
                    <strong style="<?= $is_low ? 'color:var(--danger);' : '' ?>"><?= (int)$item['quantity'] ?></strong>
                    <span style="font-size:0.75rem;color:var(--text-muted);">/ min <?= (int)$item['reorder_level'] ?></span>
                  </td>
                  <td>
                    <?= $item['expiry_date'] ? date('d M Y', strtotime($item['expiry_date'])) : 'N/A' ?>
                  </td>
                  <td>
                    <?php if ($is_expired): ?>
                      <span class="badge badge-danger">Expired</span>
                    <?php elseif ($is_near): ?>
                      <span class="badge badge-warning">Expiring Soon</span>
                    <?php elseif ($is_low): ?>
                      <span class="badge badge-danger">Low Stock</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recent Sales Card -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fa-solid fa-receipt" style="color:var(--success);margin-right:0.5rem;"></i> Recent Invoices</h3>
      <a href="<?= BASE_URL ?>/sales/index.php" class="btn btn-outline btn-sm">View Sales</a>
    </div>
    <div class="card-body" style="padding: 0;">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Bill #</th>
              <th>Customer</th>
              <th>Date / Time</th>
              <th>Total</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent_sales)): ?>
              <tr>
                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                  No sales recorded yet. Click <strong>New Sale Bill</strong> to create one.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($recent_sales as $sale): ?>
                <tr>
                  <td><strong><?= e($sale['bill_no']) ?></strong></td>
                  <td><?= e($sale['customer_name'] ?: 'Walk-in Customer') ?></td>
                  <td><?= date('d M, H:i', strtotime($sale['sale_date'])) ?></td>
                  <td><strong><?= format_currency($sale['total_amount']) ?></strong></td>
                  <td>
                    <a href="<?= BASE_URL ?>/sales/invoice.php?id=<?= $sale['sale_id'] ?>" class="btn btn-outline btn-sm" title="View & Print">
                      <i class="fa-solid fa-file-invoice"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const ctx = document.getElementById('financialChart').getContext('2d');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode($chart_labels) ?>,
      datasets: [
        {
          label: 'Sales Revenue ($)',
          data: <?= json_encode($daily_sales_data) ?>,
          borderColor: '#10b981',
          backgroundColor: 'rgba(16, 185, 129, 0.1)',
          tension: 0.3,
          fill: true,
          pointRadius: 4,
          pointHoverRadius: 6
        },
        {
          label: 'Purchases Cost ($)',
          data: <?= json_encode($daily_purchases_data) ?>,
          borderColor: '#0284c7',
          backgroundColor: 'rgba(2, 132, 199, 0.1)',
          tension: 0.3,
          fill: true,
          pointRadius: 4,
          pointHoverRadius: 6
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top' },
        tooltip: {
          callbacks: {
            label: function(context) {
              return context.dataset.label + ': $' + Number(context.raw).toFixed(2);
            }
          }
        }
      },
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
