<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';

$user = require_role('admin');
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle_status' && $id > 0) {
        $stmt = $pdo->prepare("SELECT status FROM users WHERE id = :id AND role = 'student'");
        $stmt->execute(['id' => $id]);
        $current = $stmt->fetchColumn();

        if ($current !== false) {
            $newStatus = $current === 'active' ? 'suspended' : 'active';
            $pdo->prepare('UPDATE users SET status = :status WHERE id = :id')
                ->execute(['status' => $newStatus, 'id' => $id]);
            audit_log($user['id'], 'student_status_change', "Set user #{$id} to {$newStatus}");
            flash('success', 'Student status updated.');
        }
    }

    redirect('/admin/students.php');
}

$search     = trim($_GET['q'] ?? '');
$deptFilter = (int) ($_GET['department_id'] ?? 0);

$sql = "SELECT u.*, d.name AS department_name FROM users u
        LEFT JOIN departments d ON d.id = u.department_id
        WHERE u.role = 'student'";
$params = [];

if ($search !== '') {
    $sql .= ' AND (u.full_name LIKE :search OR u.email LIKE :search OR u.identifier LIKE :search)';
    $params['search'] = "%{$search}%";
}
if ($deptFilter > 0) {
    $sql .= ' AND u.department_id = :dept';
    $params['dept'] = $deptFilter;
}
$sql .= ' ORDER BY u.full_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();

$pageTitle = 'Manage Students';
$activeNav = 'students';
require __DIR__ . '/../../includes/partials/dashboard_header.php';
?>
<h1>Manage Students</h1>

<?php if ($message = flash('success')): ?>
  <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
  <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
    <div class="form-group" style="margin:0; flex:1; min-width:200px;">
      <label>Search</label>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Name, email, or reg number">
    </div>
    <div class="form-group" style="margin:0; min-width:180px;">
      <label>Department</label>
      <select name="department_id">
        <option value="">All departments</option>
        <?php foreach ($departments as $d): ?>
          <option value="<?= (int) $d['id'] ?>" <?= $deptFilter === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
  </form>
</div>

<div class="card">
  <table style="width:100%; border-collapse: collapse;">
    <thead>
      <tr style="text-align:left; border-bottom:1px solid var(--border);">
        <th style="padding:8px;">Name</th><th style="padding:8px;">Email</th>
        <th style="padding:8px;">Dept / Level</th><th style="padding:8px;">Status</th><th style="padding:8px;"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($students as $s): ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:8px;"><?= e($s['full_name']) ?></td>
          <td style="padding:8px;"><?= e($s['email']) ?></td>
          <td style="padding:8px;"><?= e($s['department_name'] ?? '—') ?> / <?= e($s['level'] ?? '—') ?></td>
          <td style="padding:8px;">
            <span class="badge <?= $s['status'] === 'active' ? 'badge-approved' : 'badge-rejected' ?>"><?= e($s['status']) ?></span>
          </td>
          <td style="padding:8px;">
            <div style="display:flex; gap:8px;">
              <a href="/admin/student_edit.php?id=<?= (int) $s['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
              <form method="post" onsubmit="return confirm('<?= $s['status'] === 'active' ? 'Suspend' : 'Reactivate' ?> this student?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm"><?= $s['status'] === 'active' ? 'Suspend' : 'Reactivate' ?></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$students): ?>
        <tr><td colspan="5" class="muted" style="padding:8px;">No students found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/partials/dashboard_footer.php'; ?>
