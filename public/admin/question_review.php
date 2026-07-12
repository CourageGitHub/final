<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = require_role('admin');
$pdo  = db();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT pq.*, c.course_code, c.title AS course_title
     FROM past_questions pq JOIN courses c ON c.id = pq.course_id
     WHERE pq.id = :id'
);
$stmt->execute(['id' => $id]);
$paper = $stmt->fetch();

if (!$paper) {
    http_response_code(404);
    die('Past question not found.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_items') {
        $numbers  = $_POST['question_number'] ?? [];
        $contents = $_POST['content'] ?? [];

        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM question_items WHERE past_question_id = :id')->execute(['id' => $id]);
        $insert = $pdo->prepare(
            'INSERT INTO question_items (past_question_id, question_number, content) VALUES (:pq, :num, :content)'
        );
        foreach ($numbers as $i => $num) {
            $num     = trim((string) $num);
            $content = trim((string) ($contents[$i] ?? ''));
            if ($num !== '' && $content !== '') {
                $insert->execute(['pq' => $id, 'num' => $num, 'content' => $content]);
            }
        }
        $pdo->commit();

        audit_log($user['id'], 'question_items_edit', "Edited question items for paper #{$id}");
        flash('success', 'Questions updated.');
        redirect("/admin/question_review.php?id={$id}");
    }

    if ($action === 'approve') {
        $pdo->prepare("UPDATE past_questions SET status = 'approved' WHERE id = :id")->execute(['id' => $id]);
        $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message) VALUES (NULL, :title, :message)'
        )->execute([
            'title'   => 'New past question available',
            'message' => "{$paper['course_code']} ({$paper['academic_year']}, " . ucfirst($paper['semester']) . ' semester) is now available.',
        ]);
        audit_log($user['id'], 'question_approve', "Approved paper #{$id}");
        flash('success', 'Approved and published to students.');
        redirect("/admin/question_review.php?id={$id}");
    }

    if ($action === 'reject') {
        $pdo->prepare("UPDATE past_questions SET status = 'rejected' WHERE id = :id")->execute(['id' => $id]);
        audit_log($user['id'], 'question_reject', "Rejected paper #{$id}");
        flash('success', 'Rejected.');
        redirect("/admin/question_review.php?id={$id}");
    }

    if ($action === 'delete') {
        $absolutePath = rtrim(app_config()['upload']['path'], '/') . '/' . $paper['file_path'];
        @unlink($absolutePath);
        $pdo->prepare('DELETE FROM past_questions WHERE id = :id')->execute(['id' => $id]);
        audit_log($user['id'], 'question_delete', "Deleted paper #{$id}");
        flash('success', 'Deleted.');
        redirect('/admin/questions.php');
    }
}

$itemsStmt = $pdo->prepare('SELECT * FROM question_items WHERE past_question_id = :id ORDER BY id');
$itemsStmt->execute(['id' => $id]);
$items = $itemsStmt->fetchAll();

$pageTitle = 'Review paper';
$activeNav = 'questions';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<p><a href="/admin/questions.php">&larr; Back to Past Questions</a></p>
<h1><?= e($paper['course_code']) ?> &mdash; <?= e($paper['academic_year']) ?> (<?= e(ucfirst($paper['semester'])) ?>)</h1>
<p class="muted"><?= e($paper['course_title']) ?> &middot; <?= e(ucfirst($paper['exam_type'])) ?> &middot; <span class="badge badge-<?= e($paper['status']) ?>"><?= e(ucfirst($paper['status'])) ?></span></p>

<?php if ($message = flash('success')): ?>
  <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px; display:flex; gap:10px; flex-wrap:wrap;">
  <form method="post" onsubmit="return confirm('Publish this paper to students?');">
    <?= csrf_field() ?><input type="hidden" name="action" value="approve">
    <button type="submit" class="btn btn-primary btn-sm">Approve &amp; publish</button>
  </form>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="reject">
    <button type="submit" class="btn btn-secondary btn-sm">Reject</button>
  </form>
  <form method="post" onsubmit="return confirm('Permanently delete this paper and its file?');">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete">
    <button type="submit" class="btn btn-secondary btn-sm">Delete</button>
  </form>
  <a href="/download.php?id=<?= (int) $paper['id'] ?>" class="btn btn-secondary btn-sm">Download original file</a>
</div>

<?php if (!$paper['extracted_text']): ?>
  <div class="alert alert-error">
    No text could be extracted automatically (OCR/PDF tools may not be installed, or the scan quality is poor).
    Download the original file above and add questions manually below.
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;">
  <h2>Questions <span class="ai-badge">Auto-split</span></h2>
  <p class="muted">Fix any numbering or text mistakes before approving — students will only ever see what's saved here.</p>
  <form method="post" id="itemsForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_items">
    <div id="itemsWrap">
      <?php foreach ($items as $item): ?>
        <div class="form-group" style="display:flex; gap:10px; align-items:flex-start;">
          <input type="text" name="question_number[]" value="<?= e($item['question_number']) ?>" style="width:70px;" placeholder="No.">
          <textarea name="content[]" rows="3" style="flex:1;"><?= e($item['content']) ?></textarea>
          <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.remove()">Remove</button>
        </div>
      <?php endforeach; ?>
      <?php if (!$items): ?>
        <p class="muted">No questions parsed yet — add one manually below.</p>
      <?php endif; ?>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" id="addItem">+ Add question</button>
    <div style="margin-top:16px;"><button type="submit" class="btn btn-primary">Save questions</button></div>
  </form>
</div>

<?php if ($paper['extracted_text']): ?>
<div class="card">
  <h2>Raw extracted text (for reference)</h2>
  <textarea readonly rows="10" style="width:100%; font-family: monospace; font-size: 0.85rem;"><?= e($paper['extracted_text']) ?></textarea>
</div>
<?php endif; ?>

<script>
  document.getElementById('addItem').addEventListener('click', function () {
    var wrap = document.getElementById('itemsWrap');
    var row = document.createElement('div');
    row.className = 'form-group';
    row.style = 'display:flex; gap:10px; align-items:flex-start;';
    row.innerHTML = '<input type="text" name="question_number[]" style="width:70px;" placeholder="No.">'
      + '<textarea name="content[]" rows="3" style="flex:1;"></textarea>'
      + '<button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.remove()">Remove</button>';
    wrap.appendChild(row);
  });
</script>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
