<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = require_role('admin');
$pdo  = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_room') {
        $name     = trim($_POST['room_name'] ?? '');
        $capacity = (int) ($_POST['capacity'] ?? 40);

        if ($name === '') {
            $errors[] = 'Enter a room name.';
        } else {
            try {
                $pdo->prepare('INSERT INTO rooms (name, capacity) VALUES (:name, :cap)')
                    ->execute(['name' => $name, 'cap' => max($capacity, 1)]);
                flash('success', 'Room added.');
                redirect('/admin/timetable.php');
            } catch (PDOException $e) {
                $errors[] = 'That room name already exists.';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM exam_timetable_entries WHERE id = :id')->execute(['id' => $id]);
        audit_log($user['id'], 'timetable_delete', "Removed exam slot #{$id}");
        flash('success', 'Exam slot removed.');
        redirect('/admin/timetable.php');
    }

    if ($action === 'create') {
        $courseId  = (int) ($_POST['course_id'] ?? 0);
        $roomId    = (int) ($_POST['room_id'] ?? 0);
        $examDate  = $_POST['exam_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $endTime   = $_POST['end_time'] ?? '';
        $session   = trim($_POST['academic_session'] ?? '');
        $semester  = $_POST['semester'] ?? '';

        if ($courseId <= 0) $errors[] = 'Select a course.';
        if ($roomId <= 0) $errors[] = 'Select a room.';
        if ($examDate === '') $errors[] = 'Pick an exam date.';
        if ($startTime === '' || $endTime === '') $errors[] = 'Enter a start and end time.';
        if ($startTime !== '' && $endTime !== '' && $startTime >= $endTime) $errors[] = 'End time must be after start time.';
        if (!preg_match('/^\d{4}\/\d{4}$/', $session)) $errors[] = 'Academic session must look like 2023/2024.';
        if (!in_array($semester, ['first', 'second'], true)) $errors[] = 'Select a valid semester.';

        // App-level clash checks (friendly messages) - the DB UNIQUE key below
        // is the final safety net in case of a race between two admins.
        if (!$errors) {
            $roomClash = $pdo->prepare(
                'SELECT id FROM exam_timetable_entries
                 WHERE room_id = :room AND exam_date = :date
                   AND NOT (end_time <= :start OR start_time >= :end)'
            );
            $roomClash->execute(['room' => $roomId, 'date' => $examDate, 'start' => $startTime, 'end' => $endTime]);
            if ($roomClash->fetch()) {
                $errors[] = 'That room is already booked for an overlapping time on this date.';
            }
        }

        if (!$errors) {
            $courseInfo = $pdo->prepare('SELECT department_id, level, course_code FROM courses WHERE id = :id');
            $courseInfo->execute(['id' => $courseId]);
            $ci = $courseInfo->fetch();

            $classClash = $pdo->prepare(
                'SELECT e.id FROM exam_timetable_entries e
                 JOIN courses c ON c.id = e.course_id
                 WHERE c.department_id = :dept AND c.level = :level AND e.exam_date = :date
                   AND NOT (e.end_time <= :start OR e.start_time >= :end)'
            );
            $classClash->execute([
                'dept' => $ci['department_id'], 'level' => $ci['level'], 'date' => $examDate,
                'start' => $startTime, 'end' => $endTime,
            ]);
            if ($classClash->fetch()) {
                $errors[] = 'Students in that department/level already have an overlapping exam at that time.';
            }
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO exam_timetable_entries (course_id, room_id, exam_date, start_time, end_time, academic_session, semester)
                     VALUES (:course, :room, :date, :start, :end, :session, :semester)'
                );
                $stmt->execute([
                    'course' => $courseId, 'room' => $roomId, 'date' => $examDate,
                    'start' => $startTime, 'end' => $endTime, 'session' => $session, 'semester' => $semester,
                ]);
                $newId = (int) $pdo->lastInsertId();

                $pdo->prepare(
                    'INSERT INTO notifications (user_id, title, message) VALUES (NULL, :title, :message)'
                )->execute([
                    'title'   => 'Examination timetable updated',
                    'message' => "{$ci['course_code']} exam scheduled for {$examDate}, {$startTime}\u{2013}{$endTime}.",
                ]);

                audit_log($user['id'], 'timetable_create', "Added exam slot #{$newId}");
                flash('success', 'Exam slot added — students notified.');
                redirect('/admin/timetable.php');
            } catch (PDOException $e) {
                $errors[] = 'Could not save this slot — it looks like a duplicate booking.';
            }
        }
    }
}

$courses = $pdo->query('SELECT id, course_code, title FROM courses ORDER BY course_code')->fetchAll();
$rooms   = $pdo->query('SELECT id, name, capacity FROM rooms ORDER BY name')->fetchAll();
$entries = $pdo->query(
    'SELECT e.*, c.course_code, c.title AS course_title, r.name AS room_name
     FROM exam_timetable_entries e
     JOIN courses c ON c.id = e.course_id
     JOIN rooms r ON r.id = e.room_id
     ORDER BY e.exam_date, e.start_time'
)->fetchAll();

$pageTitle = 'Timetable';
$activeNav = 'timetable';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>Examination Timetable</h1>

<?php foreach ($errors as $error): ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>
<?php if ($message = flash('success')): ?>
  <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>

<?php if (!$rooms): ?>
<div class="card" style="margin-bottom:24px;">
  <h2>Add a room first</h2>
  <form method="post" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_room">
    <div class="form-group" style="margin:0;"><label>Room name</label><input type="text" name="room_name" placeholder="Hall A" required></div>
    <div class="form-group" style="margin:0;"><label>Capacity</label><input type="text" name="capacity" value="40" style="width:100px;"></div>
    <button type="submit" class="btn btn-primary">Add room</button>
  </form>
</div>
<?php elseif (!$courses): ?>
  <div class="alert alert-error">No courses yet — <a href="/admin/courses.php">add one first</a>.</div>
<?php else: ?>
<div class="card" style="margin-bottom:24px;">
  <h2>Schedule an exam</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div style="display:flex; gap:12px; flex-wrap:wrap;">
      <div class="form-group" style="flex:1; min-width:200px;">
        <label>Course</label>
        <select name="course_id" required>
          <option value="">Select course</option>
          <?php foreach ($courses as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= e($c['course_code']) ?> — <?= e($c['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="flex:1; min-width:150px;">
        <label>Room</label>
        <select name="room_id" required>
          <option value="">Select room</option>
          <?php foreach ($rooms as $r): ?>
            <option value="<?= (int) $r['id'] ?>"><?= e($r['name']) ?> (cap. <?= (int) $r['capacity'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div style="display:flex; gap:12px; flex-wrap:wrap;">
      <div class="form-group" style="flex:1; min-width:150px;"><label>Exam date</label><input type="date" name="exam_date" required></div>
      <div class="form-group" style="flex:1; min-width:120px;"><label>Start time</label><input type="time" name="start_time" required></div>
      <div class="form-group" style="flex:1; min-width:120px;"><label>End time</label><input type="time" name="end_time" required></div>
    </div>
    <div style="display:flex; gap:12px; flex-wrap:wrap;">
      <div class="form-group" style="flex:1; min-width:150px;"><label>Academic session</label><input type="text" name="academic_session" placeholder="2023/2024" required></div>
      <div class="form-group" style="flex:1; min-width:150px;">
        <label>Semester</label>
        <select name="semester" required>
          <option value="first">First</option>
          <option value="second">Second</option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Add exam slot</button>
  </form>
</div>

<details style="margin-bottom:24px;">
  <summary style="cursor:pointer; font-weight:600; margin-bottom:8px;">Add another room</summary>
  <form method="post" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin-top:12px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_room">
    <div class="form-group" style="margin:0;"><label>Room name</label><input type="text" name="room_name" placeholder="Hall B" required></div>
    <div class="form-group" style="margin:0;"><label>Capacity</label><input type="text" name="capacity" value="40" style="width:100px;"></div>
    <button type="submit" class="btn btn-secondary">Add room</button>
  </form>
</details>
<?php endif; ?>

<div class="card">
  <h2>Scheduled exams</h2>
  <table style="width:100%; border-collapse: collapse;">
    <thead>
      <tr style="text-align:left; border-bottom:1px solid var(--border);">
        <th style="padding:8px;">Course</th><th style="padding:8px;">Date</th>
        <th style="padding:8px;">Time</th><th style="padding:8px;">Venue</th><th style="padding:8px;"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($entries as $e): ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:8px;"><?= e($e['course_code']) ?> — <?= e($e['course_title']) ?></td>
          <td style="padding:8px;"><?= e(date('D, d M Y', strtotime($e['exam_date']))) ?></td>
          <td style="padding:8px;"><?= e(date('g:ia', strtotime($e['start_time']))) ?>–<?= e(date('g:ia', strtotime($e['end_time']))) ?></td>
          <td style="padding:8px;"><?= e($e['room_name']) ?></td>
          <td style="padding:8px;">
            <form method="post" onsubmit="return confirm('Remove this exam slot?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$entries): ?>
        <tr><td colspan="5" class="muted" style="padding:8px;">Nothing scheduled yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
