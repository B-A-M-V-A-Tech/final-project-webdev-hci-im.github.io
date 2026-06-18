<?php
header('Content-Type: text/html; charset=utf-8');
include __DIR__ . '/../database/db_connect.php';
include __DIR__ . '/../database/powerbi_data_helper.php';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
$baseUrl = $scheme . '://' . $host . $basePath;
$feeds = powerbiFeedCatalog($baseUrl);

$counts = array();
foreach (array_keys($feeds) as $resource) {
    try {
        $counts[$resource] = count(powerbiDatasetRows($conn, $resource));
    } catch (Throwable $e) {
        $counts[$resource] = 0;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sip &amp; Pulse — Power BI Data Feeds</title>
  <style>
    body { font-family: Segoe UI, sans-serif; max-width: 920px; margin: 32px auto; padding: 0 20px; color: #1f150c; line-height: 1.55; }
    h1 { font-size: 1.5rem; margin-bottom: 4px; }
    .sub { color: #6b5a48; font-size: .92rem; margin-bottom: 24px; }
    table { width: 100%; border-collapse: collapse; margin: 16px 0 28px; font-size: .88rem; }
    th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; vertical-align: top; }
    th { background: #74512d; color: #f8f4e1; }
    code { background: #f4efe6; padding: 2px 6px; border-radius: 4px; word-break: break-all; font-size: .8rem; }
    .box { background: #faf7f0; border: 1px solid #e2d8c8; border-radius: 10px; padding: 16px 18px; margin-bottom: 18px; }
    .box h2 { margin: 0 0 8px; font-size: 1rem; color: #74512d; }
    ul { margin: 8px 0 0 18px; padding: 0; }
    a { color: #74512d; }
  </style>
</head>
<body>
  <h1>Power BI Data Feeds — Sip &amp; Pulse</h1>
  <p class="sub">Use <strong>ONE URL</strong> below in Power BI Desktop → <strong>Get data → Web</strong>. Check all 6 sheets in Navigator, then Load.</p>

  <div class="box" style="border-color:#74512d;background:#fff8ef;">
    <h2>⭐ ONE URL — all 6 tables (recommended)</h2>
    <p><code><?php echo htmlspecialchars($baseUrl . '/api/powerbi_all.php'); ?></code></p>
    <p><a href="<?php echo htmlspecialchars($baseUrl . '/api/powerbi_all.php'); ?>" target="_blank"><strong>Download / test Excel bundle</strong></a></p>
    <p style="font-size:.85rem;margin:8px 0 0;">Power BI Navigator: i-check ang lahat — <strong>Orders, Order Lines, Menu, Reviews, Daily, Calendar</strong> → Load.</p>
  </div>

  <div class="box">
    <h2>Or import each table separately (optional)</h2>
    <table>
      <thead>
        <tr><th>Table</th><th>Rows</th><th>CSV URL (copy to Power BI)</th></tr>
      </thead>
      <tbody>
        <?php foreach ($feeds as $name => $urls): ?>
        <tr>
          <td><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $name))); ?></strong></td>
          <td><?php echo intval($counts[$name] ?? 0); ?></td>
          <td><code><?php echo htmlspecialchars($urls['csv']); ?></code><br><a href="<?php echo htmlspecialchars($urls['csv']); ?>" target="_blank">Test in browser</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="box">
    <h2>Relationships to create in Power BI Model view</h2>
    <ul>
      <li><code>Orders[order_id]</code> → <code>Order Lines[order_id]</code> (One to many)</li>
      <li><code>Calendar[date]</code> → <code>Orders[order_date]</code> (One to many, date part only)</li>
      <li><code>Calendar[date]</code> → <code>Daily[ sale_date]</code> (One to one)</li>
      <li><code>Menu[product_name]</code> → <code>Order Lines[product_name]</code> (Many to one)</li>
    </ul>
  </div>

  <div class="box">
    <h2>Refresh (rubric: Data Refresh 5 pts)</h2>
    <p>After new orders: <strong>Home → Refresh</strong> in Power BI Desktop. When published online: Dataset → <strong>Scheduled refresh</strong> every 1 hour (or use gateway for localhost Web URL).</p>
  </div>
</body>
</html>
