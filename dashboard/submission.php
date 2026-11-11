<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
$pdo=db();
$id=trim((string)($_GET['id'] ?? ''));
$stmt=$pdo->prepare('SELECT * FROM submissions WHERE submission_id=:id LIMIT 1'); $stmt->execute(['id'=>$id]); $s=$stmt->fetch();
if(!$s){ http_response_code(404); $pageTitle='Submission not found'; include __DIR__.'/../includes/admin_header.php'; echo '<section class="panel empty-page"><h1>Submission not found</h1><p>The requested entry does not exist.</p><a class="gold-button" href="'.admin_e(admin_url('/dashboard/submissions')).'">Back to submissions</a></section>'; include __DIR__.'/../includes/admin_footer.php'; exit; }
ensure_submission_status($pdo,$id); $currentStatus=admin_status($pdo,$id); $message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!admin_check_csrf($_POST['csrf'] ?? null)){ $message='Invalid session token. Please refresh and try again.'; }
  else { $new=trim((string)($_POST['status']??'')); if(!in_array($new,admin_statuses(),true)){ $message='Invalid status selected.'; } else { $u=$pdo->prepare('UPDATE man_submission_status SET status=:status, updated_at=NOW(), updated_by=:admin WHERE submission_id=:id'); $u->execute(['status'=>$new,'admin'=>(int)admin_user()['id'],'id'=>$id]); $currentStatus=$new; $message='Status updated successfully.'; } }
}
$pageTitle='Submission '.$s['submission_id']; $active='submissions'; include __DIR__.'/../includes/admin_header.php';
?>
<div class="detail-top reveal"><div><a class="back-link" href="<?= admin_url('/dashboard/submissions') ?>">← Back to submissions</a><div class="eyebrow">Submission</div><h1><?= admin_e($s['artist_name']) ?></h1><p><?= admin_e($s['song_title']) ?> · #<?= admin_e($s['submission_id']) ?></p></div><div class="detail-actions"><span class="status-pill <?= status_badge_class($currentStatus) ?> large"><?= admin_e($currentStatus) ?></span><span class="payment-pill <?= $s['payment_status']==='paid'?'paid':($s['payment_status']==='failed'?'failed':'pending') ?> large">Payment: <?= admin_e(ucfirst($s['payment_status'])) ?></span></div></div>
<?php if($message): ?><div class="flash-message reveal"><?= admin_e($message) ?></div><?php endif; ?>
<div class="detail-grid reveal">
<section class="panel"><div class="panel-heading"><div><div class="eyebrow">Artist</div><h2>Artist information</h2></div></div><div class="info-grid">
<?php foreach([['Category',$s['category']],['Artist / Band',$s['artist_name']],['Address',$s['address']],['Contact Name',$s['contact_name']],['Contact Number',$s['contact_number']],['Band / Website / Social',$s['band_page']]] as $item): ?><div class="info-item"><span><?= admin_e($item[0]) ?></span><?php if($item[0]==='Band / Website / Social'): ?><a href="<?= admin_e($item[1]) ?>" target="_blank" rel="noopener noreferrer"><?= admin_e($item[1]) ?></a><?php else: ?><strong><?= nl2br(admin_e($item[1])) ?></strong><?php endif; ?></div><?php endforeach; ?></div></section>
<section class="panel"><div class="panel-heading"><div><div class="eyebrow">Song</div><h2>Song information</h2></div></div><div class="info-grid">
<?php foreach([['Song Title',$s['song_title']],['Songwriter',$s['songwriter']],['Producer',$s['producer']],['Sound Engineer',$s['sound_engineer']],['Video Director',$s['video_director'] ?: 'Not provided'],['Recorded At',$s['recorded_at']]] as $item): ?><div class="info-item"><span><?= admin_e($item[0]) ?></span><strong><?= nl2br(admin_e($item[1])) ?></strong></div><?php endforeach; ?></div></section>
<section class="panel media-panel"><div class="panel-heading"><div><div class="eyebrow">Media</div><h2>Audio &amp; video</h2></div></div><div class="media-links"><a href="<?= admin_e($s['platform_audio_link']) ?>" target="_blank" rel="noopener noreferrer"><span class="icon-badge amber"><?= admin_icon('music') ?></span><div><strong>Music platform</strong><small><?= admin_e($s['platform_audio_link']) ?></small></div><b><?= admin_icon('external-link') ?></b></a><a href="<?= admin_e($s['music_video_link']) ?>" target="_blank" rel="noopener noreferrer"><span class="icon-badge violet"><?= admin_icon('video') ?></span><div><strong>Music video</strong><small><?= admin_e($s['music_video_link']) ?></small></div><b><?= admin_icon('external-link') ?></b></a></div></section>
<section class="panel lyrics-panel"><div class="panel-heading"><div><div class="eyebrow">Lyrics</div><h2>Submitted lyrics</h2></div></div><pre class="lyrics-box"><?= admin_e($s['lyrics']) ?></pre></section>
<section class="panel status-panel"><div class="panel-heading"><div><div class="eyebrow">Workflow</div><h2>Submission status</h2></div></div><form method="post" class="status-form"><input type="hidden" name="csrf" value="<?= admin_e(admin_csrf()) ?>"><div class="status-pill-picker"><?php foreach(admin_statuses() as $st): $rid='st_'.md5($st); ?><input class="status-radio" type="radio" name="status" id="<?= $rid ?>" value="<?= admin_e($st) ?>" <?= $currentStatus===$st?'checked':'' ?>><label class="status-radio-label" for="<?= $rid ?>"><?= admin_e($st) ?></label><?php endforeach; ?></div><button class="gold-button" type="submit">Save status <?= admin_icon('arrow-right') ?></button></form><div class="meta-line">Submitted <?= admin_e(date('d M Y, h:i A', strtotime((string)$s['created_at']))) ?></div></section>
</div>
<?php include __DIR__.'/../includes/admin_footer.php'; ?>
