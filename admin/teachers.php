<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('teachers');
$title = 'Teachers';
$active = 'teachers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $data = [
            'teacher_code' => strtoupper(trim($_POST['teacher_code'])),
            'name' => trim($_POST['name']),
            'email' => strtolower(trim($_POST['email'])),
            'program_id' => (int) ($_POST['program_id'] ?? 0) ?: null,
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'qualification' => trim($_POST['qualification'] ?? ''),
        ];
        if ($data['teacher_code'] === '' || $data['name'] === '' || $data['email'] === '' || $data['program_id'] === null) {
            flash('danger', 'Teacher code, name, email and program are required.');
        } elseif (!db_val('SELECT COUNT(*) FROM programs WHERE id = ? AND status = 1', [$data['program_id']])) {
            flash('danger', 'Selected program is invalid.');
        } else {
            try {
                if ($id > 0) {
                    db_run('UPDATE teachers SET teacher_code=?, name=?, email=?, program_id=?, phone=?, address=?, qualification=? WHERE id=?',
                        [$data['teacher_code'], $data['name'], $data['email'], $data['program_id'], $data['phone'] ?: null, $data['address'] ?: null, $data['qualification'] ?: null, $id]);
                    db_run('UPDATE users SET username=? WHERE teacher_id=?', [$data['email'], $id]);
                    audit('Edit teacher', 'Updated teacher: ' . $data['name'], $id);
                    flash('success', 'Teacher updated.');
                } else {
                    $pass = $_POST['password'] ?? '';
                    if (strlen($pass) < 4) {
                        flash('danger', 'Default password must be at least 4 characters.');
                        redirect(url('admin/teachers.php'));
                    }
                    db_run('INSERT INTO teachers (teacher_code, name, email, program_id, phone, address, qualification) VALUES (?,?,?,?,?,?,?)',
                        [$data['teacher_code'], $data['name'], $data['email'], $data['program_id'], $data['phone'] ?: null, $data['address'] ?: null, $data['qualification'] ?: null]);
                    $tid = last_id();
                    db_run('INSERT INTO users (username, password, role, teacher_id) VALUES (?,?,?,?)',
                        [$data['email'], password_hash($pass, PASSWORD_DEFAULT), 'teacher', $tid]);
                    db_run('UPDATE teachers SET user_id=? WHERE id=?', [last_id(), $tid]);
                    audit('Add teacher', 'Created teacher: ' . $data['name'], $tid);
                    flash('success', 'Teacher added. Login: ' . $data['email'] . ' / password: ' . $pass);
                }
            } catch (PDOException $ex) {
                flash('danger', 'Duplicate email/code or database error: ' . $ex->getMessage());
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int) $_POST['id'];
        db_run('UPDATE teachers SET status = 1 - status WHERE id=?', [$id]);
        $uid = db_val('SELECT user_id FROM teachers WHERE id=?', [$id]);
        if ($uid) db_run('UPDATE users SET status = (SELECT status FROM teachers WHERE id=?) WHERE id=?', [$id, $uid]);
        audit('Toggle teacher', 'Activated/deactivated teacher id ' . $id, $id);
        flash('success', 'Teacher status changed.');
    } elseif ($action === 'reset_pass') {
        $id = (int) $_POST['id'];
        $np = $_POST['newpass'] ?? '';
        if (strlen($np) >= 4) {
            db_run('UPDATE users SET password=? WHERE teacher_id=?', [password_hash($np, PASSWORD_DEFAULT), $id]);
            audit('Reset teacher password', 'Teacher id ' . $id, $id);
            flash('success', 'Password reset.');
        } else {
            flash('danger', 'Password must be at least 4 characters.');
        }
    }
    redirect(url('admin/teachers.php'));
}

$edit = !empty($_GET['edit']) ? db_one('SELECT * FROM teachers WHERE id=?', [(int) $_GET['edit']]) : null;
$rows = db_all('SELECT t.*, p.name AS program_name,
                (SELECT COUNT(*) FROM teacher_subjects ts WHERE ts.teacher_id = t.id AND ts.status=1) sub_count
                FROM teachers t LEFT JOIN programs p ON p.id = t.program_id ORDER BY t.created_at DESC');
$programs = db_all('SELECT * FROM programs WHERE status=1 ORDER BY name');

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-person-video3 me-2"></i>Teachers</h4>
    <a class="btn btn-rms" href="teacher_subjects.php"><i class="bi bi-person-check me-1"></i> Assign Subjects</a>
    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#teacherModal"><i class="bi bi-person-plus me-1"></i> Add Teacher</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Code</th><th>Teacher</th><th>Email</th><th>Program</th><th>Phone</th><th>Qualification</th><th>Subjects</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><span class="badge text-bg-dark"><?php echo e($r['teacher_code']); ?></span></td>
                    <td class="fw-semibold"><?php echo e($r['name']); ?></td>
                    <td><?php echo e($r['email']); ?></td>
                    <td><?php echo e($r['program_name'] ?? '—'); ?></td>
                    <td><?php echo e($r['phone'] ?: '—'); ?></td>
                    <td class="small"><?php echo e($r['qualification'] ?: '—'); ?></td>
                    <td>
                        <a class="badge text-bg-primary text-decoration-none" href="teacher_subjects.php?teacher_id=<?php echo $r['id']; ?>"><?php echo $r['sub_count']; ?> subject(s)</a>
                    </td>
                    <td><?php echo bool_badge($r['status']); ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo $r['id']; ?>#modal"><i class="bi bi-pencil"></i></a>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete();">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-power"></i></button>
                        </form>
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetPwModal" onclick="document.getElementById('resetTeacherId').value=<?php echo $r['id']; ?>"><i class="bi bi-key"></i></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="9" class="text-center py-4 text-muted">No teachers yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="resetPwModal" tabindex="-1"><div class="modal-dialog modal-sm"><form class="modal-content" method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="reset_pass"><input type="hidden" id="resetTeacherId" name="id">
    <div class="modal-header"><h6 class="modal-title">Reset Teacher Password</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><label class="form-label">New Password</label>
        <input type="text" class="form-control" name="newpass" minlength="4" required></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning">Reset</button></div>
</form></div></div>

<div class="modal fade" id="teacherModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <?php echo csrf_field(); ?>
            <div class="modal-header"><h5 class="modal-title"><?php echo $edit ? 'Edit Teacher' : 'Add Teacher'; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int) ($edit['id'] ?? 0); ?>">
                <div class="row g-3">
                    <div class="col-6"><label class="form-label">Teacher Code *</label>
                        <input class="form-control" name="teacher_code" value="<?php echo e($edit['teacher_code'] ?? ''); ?>" placeholder="FAC001" required></div>
                    <div class="col-6"><label class="form-label">Full Name *</label>
                        <input class="form-control" name="name" value="<?php echo e($edit['name'] ?? ''); ?>" required></div>
                    <div class="col-12"><label class="form-label">Program *</label>
                        <select class="form-select" name="program_id" required>
                            <option value="">— select program —</option>
                            <?php foreach ($programs as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo selected($edit['program_id'] ?? '', $p['id']); ?>><?php echo e($p['name']); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-md-6"><label class="form-label">Email (login) *</label>
                        <input type="email" class="form-control" name="email" value="<?php echo e($edit['email'] ?? ''); ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Phone</label>
                        <input class="form-control" name="phone" value="<?php echo e($edit['phone'] ?? ''); ?>"></div>
                    <div class="col-md-6"><label class="form-label">Qualification</label>
                        <input class="form-control" name="qualification" value="<?php echo e($edit['qualification'] ?? ''); ?>" placeholder="M.Sc. CS"></div>
                    <div class="col-md-6"><label class="form-label">Address</label>
                        <input class="form-control" name="address" value="<?php echo e($edit['address'] ?? ''); ?>"></div>
                    <?php if (!$edit): ?>
                    <div class="col-12"><label class="form-label">Default Password *</label>
                        <input type="text" class="form-control" name="password" value="teacher123" minlength="4" required></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-rms">Save</button></div>
        </form>
    </div>
</div>
<?php if ($edit && !empty($_GET['edit'])): ?><script>document.addEventListener('DOMContentLoaded', function () { var m = document.getElementById('teacherModal'); if (m && window.bootstrap) new bootstrap.Modal(m).show(); });</script><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>