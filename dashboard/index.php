<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
$pdo = db();

$total = (int)$pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
$paid = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE payment_status = 'paid'")->fetchColumn();
$pendingPayment = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE payment_status = 'pending'")->fetchColumn();
$failedPayment = (int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE payment_status = 'failed'")->fetchColumn();
$reviewCounts = array_fill_keys(admin_statuses(), 0);
$rows = $pdo->query('SELECT status, COUNT(*) AS c FROM man_submission_status GROUP BY status')->fetchAll();
foreach ($rows as $r) $reviewCounts[(string)$r['status']] = (int)$r['c'];
$reviewed = $reviewCounts['Reviewed'] + $reviewCounts['Approved'] + $reviewCounts['Rejected'];

$recent = $pdo->query('SELECT s.* , COALESCE(ms.status, \'Submitted\') AS review_status FROM submissions s LEFT JOIN man_submission_status ms ON ms.submission_id=s.submission_id ORDER BY s.created_at DESC LIMIT 8')->fetchAll();

// Category breakdown + every submission grouped by category, so the dashboard
// can show category "cards" that drop down to reveal each entry inline
// (no separate page navigation needed).
$catRows = $pdo->query('SELECT category, COUNT(*) AS c FROM submissions GROUP BY category ORDER BY c DESC, category ASC')->fetchAll();
$byCategory = [];
if ($catRows) {
    $allForCats = $pdo->query('SELECT s.*, COALESCE(ms.status, \'Submitted\') AS review_status FROM submissions s LEFT JOIN man_submission_status ms ON ms.submission_id=s.submission_id ORDER BY s.artist_name ASC')->fetchAll();
    foreach ($allForCats as $r) { $byCategory[(string)$r['category']][] = $r; }
}

function payment_class(string $status): string
{
    return $status === 'paid' ? 'paid' : ($status === 'failed' ? 'failed' : 'pending');
}

$pageTitle = 'Dashboard'; $active = 'dashboard';
include __DIR__ . '/../includes/admin_header.php';
?>
<div class="page-heading-row reveal"><div><div class="eyebrow">Overview</div><h1>Submission Review</h1><p>Everything received through the Music Awards registration portal, in one place.</p></div><a class="gold-button" href="<?= admin_url('/dashboard/submissions') ?>">View all submissions <?= admin_icon('arrow-right') ?></a></div>

<div class="stats-grid reveal">
  <div class="stat-card stat-primary"><div class="icon-badge"><?= admin_icon('inbox') ?></div><span>Total submissions</span><strong><?= $total ?></strong><small>All entries received</small></div>
  <div class="stat-card"><div class="icon-badge green"><?= admin_icon('check-circle') ?></div><span>Paid</span><strong><?= $paid ?></strong><small>Payment confirmed</small></div>
  <div class="stat-card"><div class="icon-badge amber"><?= admin_icon('clock') ?></div><span>Pending payment</span><strong><?= $pendingPayment ?></strong><small>Awaiting payment</small></div>
  <div class="stat-card"><div class="icon-badge blue"><?= admin_icon('eye') ?></div><span>Under review</span><strong><?= $reviewCounts['Under Review'] ?></strong><small>Currently being checked</small></div>
  <div class="stat-card"><div class="icon-badge violet"><?= admin_icon('list') ?></div><span>Reviewed</span><strong><?= $reviewed ?></strong><small>Completed review states</small></div>
  <div class="stat-card"><div class="icon-badge green"><?= admin_icon('trophy') ?></div><span>Approved</span><strong><?= $reviewCounts['Approved'] ?></strong><small>Marked approved</small></div>
  <div class="stat-card"><div class="icon-badge red"><?= admin_icon('x-circle') ?></div><span>Rejected</span><strong><?= $reviewCounts['Rejected'] ?></strong><small>Marked rejected</small></div>
</div>

<section class="panel category-panel reveal">
  <div class="panel-heading">
    <div><div class="eyebrow">Breakdown</div><h2>Categories</h2></div>
    <a href="<?= admin_url('/dashboard/categories') ?>">Full page</a>
  </div>
  <div class="category-panel-body">
  <?php if (!$catRows): ?>
    <div class="empty-state">No category data yet.</div>
  <?php else: ?>
    <div class="category-cards-interactive">
      <?php foreach ($catRows as $c):
        $catName = (string)$c['category'];
        $pct = $total ? (int)round(((int)$c['c'] / $total) * 100) : 0;
      ?>
      <details class="category-block">
        <summary class="category-card">
          <div class="category-card-top"><span><?= admin_e($catName) ?></span><b><?= (int)$c['c'] ?></b></div>
          <div class="category-card-bar"><i style="width:<?= $pct ?>%"></i></div>
          <div class="category-card-foot"><small><?= $pct ?>% of all submissions</small><span class="chevron"><?= admin_icon('chevron-down') ?></span></div>
        </summary>
        <div class="category-dropdown-inner">
          <?php foreach ($byCategory[$catName] ?? [] as $s): ?>
          <div class="entry-row">
            <div class="entry-main">
              <button type="button" class="entry-name-btn" data-modal-trigger
                data-id="<?= admin_e($s['submission_id']) ?>"
                data-artist="<?= admin_e($s['artist_name']) ?>"
                data-song="<?= admin_e($s['song_title']) ?>"
                data-category="<?= admin_e($s['category']) ?>"
                data-contact-name="<?= admin_e($s['contact_name']) ?>"
                data-contact-number="<?= admin_e($s['contact_number']) ?>"
                data-payment-class="<?= payment_class((string)$s['payment_status']) ?>"
                data-payment-label="<?= admin_e(ucfirst((string)$s['payment_status'])) ?>"
                data-status-class="<?= status_badge_class((string)$s['review_status']) ?>"
                data-status-label="<?= admin_e($s['review_status']) ?>"
                data-date="<?= admin_e(date('d M Y', strtotime((string)$s['created_at']))) ?>"
                data-url="<?= admin_e(admin_url('/dashboard/submission') . '?id=' . urlencode((string)$s['submission_id'])) ?>"
              ><?= admin_e($s['artist_name']) ?></button>
              <span class="entry-song"><?= admin_e($s['song_title']) ?></span>
            </div>
            <span class="entry-meta">
              <span class="payment-pill <?= payment_class((string)$s['payment_status']) ?>"><?= admin_e(ucfirst((string)$s['payment_status'])) ?></span>
              <span class="status-pill <?= status_badge_class((string)$s['review_status']) ?>"><?= admin_e($s['review_status']) ?></span>
              <a class="view-link" href="<?= admin_url('/dashboard/submission') ?>?id=<?= urlencode((string)$s['submission_id']) ?>">View <?= admin_icon('arrow-right') ?></a>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
      </details>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  </div>
</section>

<section class="panel recent-panel reveal">
  <a class="recent-panel-link" href="<?= admin_url('/dashboard/submissions') ?>">See all</a>
  <details class="recent-details" open>
    <summary class="panel-heading recent-summary">
      <div><div class="eyebrow">Latest</div><h2>Recent submissions</h2></div>
      <span class="chevron"><?= admin_icon('chevron-down') ?></span>
    </summary>
    <div class="recent-body">
      <div class="table-wrap"><table class="admin-table"><thead><tr><th>ID</th><th>Artist / Band</th><th>Song</th><th>Category</th><th>Payment</th><th>Status</th><th></th></tr></thead><tbody>
      <?php if (!$recent): ?><tr><td colspan="7" class="empty-state">No submissions yet.</td></tr><?php endif; ?>
      <?php foreach ($recent as $s): ?><tr>
        <td><span class="id-chip">#<?= admin_e($s['submission_id']) ?></span></td>
        <td>
          <button type="button" class="entry-name-btn" data-modal-trigger
            data-id="<?= admin_e($s['submission_id']) ?>"
            data-artist="<?= admin_e($s['artist_name']) ?>"
            data-song="<?= admin_e($s['song_title']) ?>"
            data-category="<?= admin_e($s['category']) ?>"
            data-contact-name="<?= admin_e($s['contact_name']) ?>"
            data-contact-number="<?= admin_e($s['contact_number']) ?>"
            data-payment-class="<?= payment_class((string)$s['payment_status']) ?>"
            data-payment-label="<?= admin_e(ucfirst((string)$s['payment_status'])) ?>"
            data-status-class="<?= status_badge_class((string)$s['review_status']) ?>"
            data-status-label="<?= admin_e($s['review_status']) ?>"
            data-date="<?= admin_e(date('d M Y', strtotime((string)$s['created_at']))) ?>"
            data-url="<?= admin_e(admin_url('/dashboard/submission') . '?id=' . urlencode((string)$s['submission_id'])) ?>"
          ><?= admin_e($s['artist_name']) ?></button>
          <small><?= admin_e($s['contact_name']) ?></small>
        </td>
        <td><?= admin_e($s['song_title']) ?></td><td><?= admin_e($s['category']) ?></td>
        <td><span class="payment-pill <?= payment_class((string)$s['payment_status']) ?>"><?= admin_e(ucfirst((string)$s['payment_status'])) ?></span></td>
        <td><span class="status-pill <?= status_badge_class($s['review_status']) ?>"><?= admin_e($s['review_status']) ?></span></td>
        <td><a class="view-link" href="<?= admin_url('/dashboard/submission') ?>?id=<?= urlencode((string)$s['submission_id']) ?>">View</a></td>
      </tr><?php endforeach; ?></tbody></table></div>
    </div>
  </details>
</section>

<div class="entry-modal-overlay" id="entryModalOverlay" aria-hidden="true">
  <div class="entry-modal" role="dialog" aria-modal="true" aria-labelledby="entryModalTitle">
    <div class="entry-modal-head">
      <div>
        <div class="eyebrow" id="entryModalCategory">Category</div>
        <h2 id="entryModalTitle">Artist name</h2>
        <p class="entry-modal-sub" id="entryModalSong">Song title</p>
      </div>
      <button type="button" class="entry-modal-close" id="entryModalClose" aria-label="Close"><?= admin_icon('close') ?></button>
    </div>
    <div class="entry-modal-pills">
      <span class="payment-pill" id="entryModalPayment"></span>
      <span class="status-pill" id="entryModalStatus"></span>
    </div>
    <div class="info-grid entry-modal-grid">
      <div class="info-item"><span>Submission ID</span><strong id="entryModalId"></strong></div>
      <div class="info-item"><span>Submitted</span><strong id="entryModalDate"></strong></div>
      <div class="info-item"><span>Contact name</span><strong id="entryModalContactName"></strong></div>
      <div class="info-item"><span>Contact number</span><strong id="entryModalContactNumber"></strong></div>
    </div>
    <a href="#" id="entryModalLink" class="gold-button">View full submission <?= admin_icon('arrow-right') ?></a>
  </div>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
