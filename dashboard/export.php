<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_auth.php'; require_admin(); $pdo=db();
if(isset($_GET['download'])){
  $rows=$pdo->query("SELECT s.*, COALESCE(ms.status,'Submitted') AS review_status FROM submissions s LEFT JOIN man_submission_status ms ON ms.submission_id=s.submission_id ORDER BY s.created_at DESC")->fetchAll();
  header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="music-awards-submissions-'.date('Y-m-d').'.csv"');
  $out=fopen('php://output','w'); fputcsv($out,['Submission ID','Category','Artist / Band','Address','Contact Name','Contact Number','Song Title','Songwriter','Sound Engineer','Producer','Video Director','Recorded At','Music Platform Link','Music Video Link','Payment Status','Razorpay Payment ID','Review Status','Submitted At']);
  // csv_safe() prevents spreadsheet formula injection: without it, a
  // submitted field starting with =, +, -, or @ could run a formula (or,
  // in older Excel, a DDE command) as soon as someone opens this export.
  foreach($rows as $r) fputcsv($out,[csv_safe($r['submission_id']),csv_safe($r['category']),csv_safe($r['artist_name']),csv_safe($r['address']),csv_safe($r['contact_name']),csv_safe($r['contact_number']),csv_safe($r['song_title']),csv_safe($r['songwriter']),csv_safe($r['sound_engineer']),csv_safe($r['producer']),csv_safe($r['video_director']),csv_safe($r['recorded_at']),csv_safe($r['platform_audio_link']),csv_safe($r['music_video_link']),csv_safe($r['payment_status']),csv_safe($r['razorpay_payment_id']),csv_safe($r['review_status']),csv_safe($r['created_at'])]);
  fclose($out); exit;
}
$pageTitle='Export'; $active='export'; include __DIR__.'/../includes/admin_header.php';
$total=(int)$pdo->query('SELECT COUNT(*) FROM submissions')->fetchColumn();
?>
<div class="page-heading-row reveal"><div><div class="eyebrow">Data</div><h1>Export submissions</h1><p>Download the current registration data as a CSV file for offline use.</p></div></div>
<section class="panel export-card reveal"><div class="export-icon"><?= admin_icon('download') ?></div><div><div class="eyebrow">CSV export</div><h2>All submissions</h2><p><?= $total ?> records will be included. The export contains applicant, song, media, payment and workflow information. Lyrics are included as submitted.</p><a class="gold-button" href="<?= admin_url('/dashboard/export') ?>?download=1">Download CSV <?= admin_icon('download') ?></a></div></section>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
