<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = require_role('admin');
$pdo  = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $courseId     = (int) ($_POST['course_id'] ?? 0);
    $academicYear = trim($_POST['academic_year'] ?? '');
    $semester     = $_POST['semester'] ?? '';
    $examType     = $_POST['exam_type'] ?? '';
    $title        = trim($_POST['title'] ?? '');

    if ($courseId <= 0) $errors[] = 'Select a course.';
    if (!preg_match('/^\d{4}\/\d{4}$/', $academicYear)) $errors[] = 'Academic year must look like 2023/2024.';
    if (!in_array($semester, ['first', 'second'], true)) $errors[] = 'Select a valid semester.';
    if (!in_array($examType, ['midterm', 'final', 'quiz'], true)) $errors[] = 'Select a valid exam type.';
    if (empty($_FILES['paper']) || ($_FILES['paper']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Choose a file to upload.';
    }

    if (!$errors) {
        try {
            $stored = store_uploaded_file($_FILES['paper'], $courseId);

            $dupCheck = $pdo->prepare(
                'SELECT id FROM past_questions WHERE course_id = :course AND file_hash = :hash LIMIT 1'
            );
            $dupCheck->execute(['course' => $courseId, 'hash' => $stored['file_hash']]);
            if ($dupCheck->fetch()) {
                @unlink(app_config()['upload']['path'] . $stored['file_path']);
                throw new UploadException('This exact file has already been uploaded for this course.');
            }

            $absolutePath = rtrim(app_config()['upload']['path'], '/') . '/' . $stored['file_path'];
            $extractedText = null;
            try {
                $extractedText = extract_text_from_upload($absolutePath, $stored['mime_type']);
            } catch (Throwable $ocrError) {
                error_log('OCR failed: ' . $ocrError->getMessage());
                // Non-fatal - the paper still saves, just without extracted text yet.
            }

            $stmt = $pdo->prepare(
                'INSERT INTO past_questions
                    (course_id, uploaded_by, title, academic_year, semester, exam_type,
                     original_filename, file_path, mime_type, file_size, file_hash, extracted_text, status)
                 VALUES
                    (:course_id, :uploaded_by, :title, :year, :semester, :exam_type,
                     :orig_name, :file_path, :mime, :size, :hash, :text, "pending")'
            );
            $stmt->execute([
                'course_id'   => $courseId,
                'uploaded_by' => $user['id'],
                'title'       => $title !== '' ? $title : null,
                'year'        => $academicYear,
                'semester'    => $semester,
                'exam_type'   => $examType,
                'orig_name'   => $stored['original_filename'],
                'file_path'   => $stored['file_path'],
                'mime'        => $stored['mime_type'],
                'size'        => $stored['file_size'],
                'hash'        => $stored['file_hash'],
                'text'        => $extractedText,
            ]);

            $newId = (int) $pdo->lastInsertId();

            if ($extractedText !== null) {
                $items = split_into_questions($extractedText);
                $insertItem = $pdo->prepare(
                    'INSERT INTO question_items (past_question_id, question_number, content) VALUES (:pq, :num, :content)'
                );
                foreach ($items as $item) {
                    $insertItem->execute([
                        'pq'      => $newId,
                        'num'     => $item['question_number'],
                        'content' => $item['content'],
                    ]);
                }
            }

            audit_log($user['id'], 'question_upload', "Uploaded past question #{$newId}");
            flash('success', 'Uploaded. Review the extracted questions below, then approve it to publish.');
            redirect("/admin/question_review.php?id={$newId}");
        } catch (UploadException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$courses = $pdo->query(
    'SELECT c.id, c.course_code, c.title FROM courses c ORDER BY c.course_code'
)->fetchAll();

$statusFilter = $_GET['status'] ?? '';
$sql = 'SELECT pq.*, c.course_code, c.title AS course_title
        FROM past_questions pq
        JOIN courses c ON c.id = pq.course_id';
$params = [];
if (in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $sql .= ' WHERE pq.status = :status';
    $params['status'] = $statusFilter;
}
$sql .= ' ORDER BY pq.created_at DESC LIMIT 100';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$papers = $stmt->fetchAll();

$pageTitle = 'Past Questions';
$activeNav = 'questions';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>Past Questions</h1>

<?php foreach ($errors as $error): ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>
<?php if ($message = flash('success')): ?>
  <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>

<?php if (!$courses): ?>
  <div class="alert alert-error">No courses yet — <a href="/admin/courses.php">add one first</a> before uploading a paper.</div>
<?php else: ?>
<div class="card" style="margin-bottom: 24px;">
  <h2>Upload a paper</h2>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="form-group">
      <label>Course</label>
      <select name="course_id" required>
        <option value="">Select course</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?= (int) $c['id'] ?>"><?= e($c['course_code']) ?> — <?= e($c['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>Title (optional)</label>
      <input type="text" name="title" placeholder="e.g. End of Semester Exam">
    </div>

    <div class="form-group">
      <label>Academic year</label>
      <input type="text" name="academic_year" placeholder="2023/2024" pattern="\d{4}/\d{4}" required>
    </div>

    <div class="form-group">
      <label>Semester</label>
      <select name="semester" required>
        <option value="first">First</option>
        <option value="second">Second</option>
      </select>
    </div>

    <div class="form-group">
      <label>Exam type</label>
      <select name="exam_type" required>
        <option value="final">Final</option>
        <option value="midterm">Midterm</option>
        <option value="quiz">Quiz</option>
      </select>
    </div>

    <div class="form-group">
      <label>File (PDF, DOCX, JPG, or PNG)</label>
      <div id="dropZone" style="border: 2px dashed var(--border); border-radius: var(--radius); padding: 24px; text-align: center; cursor: pointer;">
        <p class="muted" id="dropZoneText" style="margin:0;">Drag a file here, or click to browse</p>
        <input type="file" name="paper" id="paperInput" accept=".pdf,.docx,.jpg,.jpeg,.png" required style="display:none;">
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Upload &amp; extract text</button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>All papers</h2>
  <p>
    <a href="?status=" class="btn btn-secondary btn-sm">All</a>
    <a href="?status=pending" class="btn btn-secondary btn-sm">Pending</a>
    <a href="?status=approved" class="btn btn-secondary btn-sm">Approved</a>
    <a href="?status=rejected" class="btn btn-secondary btn-sm">Rejected</a>
  </p>
  <table style="width:100%; border-collapse: collapse;">
    <thead>
      <tr style="text-align:left; border-bottom:1px solid var(--border);">
        <th style="padding:8px;">Course</th><th style="padding:8px;">Year</th>
        <th style="padding:8px;">Type</th><th style="padding:8px;">Status</th>
        <th style="padding:8px;">Downloads</th><th style="padding:8px;"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($papers as $p): ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:8px;"><?= e($p['course_code']) ?></td>
          <td style="padding:8px;"><?= e($p['academic_year']) ?> (<?= e(ucfirst($p['semester'])) ?>)</td>
          <td style="padding:8px;"><?= e(ucfirst($p['exam_type'])) ?></td>
          <td style="padding:8px;"><span class="badge badge-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span></td>
          <td style="padding:8px;"><?= (int) $p['download_count'] ?></td>
          <td style="padding:8px;"><a href="/admin/question_review.php?id=<?= (int) $p['id'] ?>" class="btn btn-secondary btn-sm">Review</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$papers): ?>
        <tr><td colspan="6" style="padding:8px;" class="muted">Nothing here yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
  var dropZone = document.getElementById('dropZone');
  var input = document.getElementById('paperInput');
  var text = document.getElementById('dropZoneText');

  dropZone.addEventListener('click', function () { input.click(); });

  input.addEventListener('change', function () {
    if (input.files.length) text.textContent = input.files[0].name;
  });

  ['dragover', 'dragleave', 'drop'].forEach(function (evt) {
    dropZone.addEventListener(evt, function (e) {
      e.preventDefault();
      dropZone.style.borderColor = evt === 'dragover' ? 'var(--blue-600)' : 'var(--border)';
    });
  });

  dropZone.addEventListener('drop', function (e) {
    if (e.dataTransfer.files.length) {
      input.files = e.dataTransfer.files;
      text.textContent = e.dataTransfer.files[0].name;
    }
  });
</script>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
