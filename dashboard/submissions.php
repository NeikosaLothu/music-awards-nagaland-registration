<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
$pdo = db();
$q = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$payment = trim((string)($_GET['payment'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1)); $perPage = 15; $offset = ($page-1)*$perPage;
$where=[]; $params=[];
if ($q !== '') { $where[]='(s.submission_id LIKE :q OR s.artist_name LIKE :q OR s.song_title LIKE :q OR s.contact_name LIKE :q OR s.contact_number LIKE :q)'; $params['q']='%'.$q.'%'; }
if ($category !== '') { $where[]='s.category = :category'; $params['category']=$category; }
if (in_array($payment,['pending','paid','failed'],true)) { $where[]='s.payment_status = :payment'; $params['payment']=$payment; }
if (in_array($status,admin_statuses(),true)) { $where[]='COALESCE(ms.status, \'Submitted\') = :status'; $params['status']=$status; }
$whereSql = $where ? ' WHERE '.implode(' AND ',$where) : '';
$countStmt=$pdo->prepare("SELECT COUNT(*) FROM submissions s LEFT JOIN man_submission_status ms ON ms.submission_id=s.submission_id $whereSql"); $countStmt->execute($params); $count=(int)$countStmt->fetchColumn(); $pages=max(1,(int)ceil($count/$perPage));
$sql="SELECT s.*, COALESCE(ms.status,'Submitted') AS review_status FROM submissions s LEFT JOIN man_submission_status ms ON ms.submission_id=s.submission_id $whereSql ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset";
$stmt=$pdo->prepare($sql); foreach($params as $k=>$v)$stmt->bindValue(':'.$k,$v); $stmt->bindValue(':limit',$perPage,PDO::PARAM_INT); $stmt->bindValue(':offset',$offset,PDO::PARAM_INT); $stmt->execute(); $rows=$stmt->fetchAll();
$catRows=$pdo->query('SELECT DISTINCT category FROM submissions ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
function qs(array $extra=[]): string { return http_build_query(array_merge($_GET,$extra)); }
// Pill filters are plain links: merge the requested change into the
// current query string, drop empty values so the URL stays clean, and
// always reset pagination since the result set just changed underneath it.
function pill_url(array $overrides = []): string {
    $merged = array_merge($_GET, $overrides);
    unset($merged['page']);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($merged);
}
$pageTitle='Submissions'; $active='submissions'; include __DIR__.'/../includes/admin_header.php';
?>
<div class="page-heading-row reveal"><div><div class="eyebrow">Entries</div><h1>Submissions</h1><p><?= $count ?> matching submission<?= $count===1?'':'s' ?>.</p></div><a class="outline-button" href="<?= admin_url('/dashboard/export') ?>">Export CSV <?= admin_icon('download') ?></a></div>
<section class="panel reveal">
<div class="filter-bar">
  <div class="filter-top-row">
    <form class="search-box" method="get" style="flex:1">
      <?php foreach (['category'=>$category,'payment'=>$payment,'status'=>$status] as $k=>$v): if ($v !== ''): ?><input type="hidden" name="<?= admin_e($k) ?>" value="<?= admin_e($v) ?>"><?php endif; endforeach; ?>
      <?= admin_icon('search') ?>
      <input name="q" value="<?= admin_e($q) ?>" placeholder="Search artist, song, ID, contact...">
    </form>
    <label class="select-pill">
      <select name="category" onchange="location.href=this.value">
        <option value="<?= pill_url(['category'=>'']) ?>" <?= $category===''?'selected':'' ?>>All categories</option>
        <?php foreach ($catRows as $c): ?>
        <option value="<?= pill_url(['category'=>$c]) ?>" <?= $category===$c?'selected':'' ?>><?= admin_e($c) ?></option>
        <?php endforeach; ?>
      </select>
      <?= admin_icon('chevron-down') ?>
    </label>
    <div class="filter-actions">
      <?php if ($_GET): ?><a class="clear-filter" href="<?= admin_url('/dashboard/submissions') ?>">Clear all filters</a><?php endif; ?>
    </div>
  </div>

  <div class="pill-group">
    <span class="pill-group-label">Payment</span>
    <a class="filter-pill <?= $payment===''?'active':'' ?>" href="<?= pill_url(['payment'=>'']) ?>">All</a>
    <?php foreach (['paid'=>'Paid','pending'=>'Pending','failed'=>'Failed'] as $val=>$label): ?>
    <a class="filter-pill <?= $payment===$val?'active':'' ?>" href="<?= pill_url(['payment'=>$payment===$val?'':$val]) ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div class="pill-group">
    <span class="pill-group-label">Status</span>
    <a class="filter-pill <?= $status===''?'active':'' ?>" href="<?= pill_url(['status'=>'']) ?>">All</a>
    <?php foreach (admin_statuses() as $st): ?>
    <a class="filter-pill <?= $status===$st?'active':'' ?>" href="<?= pill_url(['status'=>$status===$st?'':$st]) ?>"><?= admin_e($st) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<div class="table-wrap"><table class="admin-table submissions-table"><thead><tr><th>#</th><th>Artist / Band</th><th>Song</th><th>Category</th><th>Contact</th><th>Payment</th><th>Status</th><th>Submitted</th><th></th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="9" class="empty-state">No submissions match your filters.</td></tr><?php endif; ?>
<?php foreach($rows as $i=>$s): ?><tr><td class="serial"><?= $offset+$i+1 ?></td><td><strong><?= admin_e($s['artist_name']) ?></strong><small>#<?= admin_e($s['submission_id']) ?></small></td><td><?= admin_e($s['song_title']) ?></td><td><?= admin_e($s['category']) ?></td><td><?= admin_e($s['contact_name']) ?><small><?= admin_e($s['contact_number']) ?></small></td><td><span class="payment-pill <?= $s['payment_status'] === 'paid' ? 'paid' : ($s['payment_status'] === 'failed' ? 'failed' : 'pending') ?>"><?= admin_e(ucfirst($s['payment_status'])) ?></span></td><td><span class="status-pill <?= status_badge_class($s['review_status']) ?>"><?= admin_e($s['review_status']) ?></span></td><td><?= admin_e(date('d M Y', strtotime((string)$s['created_at']))) ?></td><td><a class="view-link" href="<?= admin_url('/dashboard/submission') ?>?id=<?= urlencode((string)$s['submission_id']) ?>">View <?= admin_icon('arrow-right') ?></a></td></tr><?php endforeach; ?></tbody></table></div>
<?php if($pages>1): ?><div class="pagination-row"><span>Page <?= $page ?> of <?= $pages ?></span><div class="pagination-links"><?php if($page>1): ?><a href="?<?= admin_e(qs(['page'=>$page-1])) ?>">← Prev</a><?php endif; ?><?php for($p=max(1,$page-2);$p<=min($pages,$page+2);$p++): ?><a class="<?= $p===$page?'current':'' ?>" href="?<?= admin_e(qs(['page'=>$p])) ?>"><?= $p ?></a><?php endfor; ?><?php if($page<$pages): ?><a href="?<?= admin_e(qs(['page'=>$page+1])) ?>">Next →</a><?php endif; ?></div></div><?php endif; ?>
</section>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
