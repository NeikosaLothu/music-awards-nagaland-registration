<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_auth.php'; require_admin(); $pdo=db();
$total=(int)$pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn(); $rows=$pdo->query('SELECT category, COUNT(*) AS c FROM submissions GROUP BY category ORDER BY c DESC, category ASC')->fetchAll();
$pageTitle='Categories'; $active='categories'; include __DIR__.'/../includes/admin_header.php';
?>
<div class="page-heading-row reveal"><div><div class="eyebrow">Breakdown</div><h1>Categories</h1><p>See how entries are distributed across the award categories.</p></div></div>
<div class="category-cards reveal"><?php foreach($rows as $r): $pct=$total?round(((int)$r['c']/$total)*100):0; ?><a class="category-card" href="<?= admin_url('/dashboard/submissions') ?>?category=<?= urlencode((string)$r['category']) ?>"><div class="category-card-top"><span><?= admin_e($r['category']) ?></span><b><?= (int)$r['c'] ?></b></div><div class="category-card-bar"><i style="width:<?= $pct ?>%"></i></div><small><?= $pct ?>% of all submissions</small></a><?php endforeach; ?></div>
<?php if(!$rows): ?><section class="panel empty-page reveal"><h2>No submissions yet</h2><p>Category statistics will appear here when entries are received.</p></section><?php endif; ?>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
