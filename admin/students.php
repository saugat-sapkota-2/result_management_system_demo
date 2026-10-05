<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('students');
$title = 'Students';
$active = 'students';

$role = $user['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $dob = bs_parse_input($_POST['dob'] ?? '');
        $data = [
            'student_code' => strtoupper(trim($_POST['student_code'])),
            'full_name' => trim($_POST['full_name']),
            'dob' => $dob === false ? '__INVALID__' : ($dob ?: null),
            'gender' => ($_POST['gender'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'tu_reg_no' => trim($_POST['tu_reg_no'] ?? ''),
            'exam_roll_no' => trim($_POST['exam_roll_no'] ?? ''),
            'batch_id' => (int) ($_POST['batch_id'] ?? 0) ?: null,
            'section_id' => (int) ($_POST['section_id'] ?? 0) ?: null,
            'program_id' => (int) ($_POST['program_id'] ?? 0) ?: null,
            'current_semester_id' => (int) ($_POST['current_semester_id'] ?? 0) ?: null,
            'admission_year' => (int) ($_POST['admission_year'] ?? 0) ?: null,
        ];
        if ($data['dob'] === '__INVALID__') {
            flash('danger', 'Date of birth must be a valid Bikram Sambat date (format YYYY/MM/DD).');
        } elseif ($data['student_code'] === '' || $data['full_name'] === '' || $data['program_id'] === null) {
            flash('danger', 'Student code, full name and program are required.');
        } elseif (!db_val('SELECT COUNT(*) FROM programs WHERE id = ? AND status = 1', [$data['program_id']])) {
            flash('danger', 'Selected program is invalid.');
        } elseif ($data['batch_id'] && !db_val('SELECT COUNT(*) FROM batches WHERE id = ? AND program_id = ?', [$data['batch_id'], $data['program_id']])) {
            flash('danger', 'Selected batch does not belong to the program.');
        } elseif ($data['current_semester_id'] && !db_val('SELECT COUNT(*) FROM semesters WHERE id = ? AND program_id = ?', [$data['current_semester_id'], $data['program_id']])) {
            flash('danger', 'Selected semester does not belong to the program.');
        } else {
            // photo upload
            $photo = null;
            if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
                $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                    $photo = 'uploads/students/' . $data['student_code'] . '_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../' . $photo);
                } else {
                    flash('danger', 'Photo must be JPG, PNG or GIF.');
                    redirect(url('admin/students.php'));
                }
            }
            try {
                if ($id > 0) {
                    $existing = db_one('SELECT photo, current_semester_id FROM students WHERE id=?', [$id]);
                    $photo = $photo ?: ($existing['photo'] ?? null);
                    db_run('UPDATE students SET student_code=?, full_name=?, dob=?, gender=?, email=?, phone=?, address=?, photo=?, tu_reg_no=?, exam_roll_no=?, batch_id=?, section_id=?, program_id=?, current_semester_id=?, admission_year=? WHERE id=?',
                        [$data['student_code'], $data['full_name'], $data['dob'], $data['gender'] ?: null, $data['email'] ?: null, $data['phone'] ?: null, $data['address'] ?: null, $photo, $data['tu_reg_no'] ?: null, $data['exam_roll_no'] ?: null, $data['batch_id'], $data['section_id'], $data['program_id'], $data['current_semester_id'], $data['admission_year'], $id]);
                    // keep student username in sync
                    db_run('UPDATE users SET username=? WHERE student_id=?', [$data['student_code'], $id]);
                    audit('Edit student', 'Updated student: ' . $data['full_name'] . ' (' . $data['student_code'] . ')', $id);
                    $enr = null;
                    if ($data['current_semester_id'] !== (int) ($existing['current_semester_id'] ?? 0)) {
                        $enr = auto_enroll_student($id);
                    }
                    flash('success', 'Student updated.' . ($enr && $enr['configured'] ? " Auto-enrolled {$enr['new']} subject(s) for the current semester." : ''));
                } else {
                    $pass = $_POST['password'] ?? '';
                    if (strlen($pass) < 4) {
                        flash('danger', 'Default password must be at least 4 characters.');
                        redirect(url('admin/students.php'));
                    }
                    db_run('INSERT INTO students (student_code, full_name, dob, gender, email, phone, address, photo, tu_reg_no, exam_roll_no, batch_id, section_id, program_id, current_semester_id, admission_year) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                        [$data['student_code'], $data['full_name'], $data['dob'], $data['gender'] ?: null, $data['email'] ?: null, $data['phone'] ?: null, $data['address'] ?: null, $photo, $data['tu_reg_no'] ?: null, $data['exam_roll_no'] ?: null, $data['batch_id'], $data['section_id'], $data['program_id'], $data['current_semester_id'], $data['admission_year']]);
                    $studentId = last_id();
                    db_run('INSERT INTO users (username, password, role, student_id) VALUES (?,?,?,?)',
                        [$data['student_code'], password_hash($pass, PASSWORD_DEFAULT), 'student', $studentId]);
                    db_run('UPDATE students SET user_id = ? WHERE id = ?', [last_id(), $studentId]);
                    audit('Add student', 'Created student: ' . $data['full_name'] . ' (' . $data['student_code'] . ')', $studentId);
                    $enr = $data['current_semester_id'] ? auto_enroll_student($studentId) : null;
                    flash('success', 'Student added. Login: ' . $data['student_code'] . ' / password: ' . $pass . ($enr && $enr['configured'] ? " Auto-enrolled {$enr['new']} subject(s) for the current semester." : ''));
                }
            } catch (PDOException $ex) {
                flash('danger', 'Duplicate student code or database error: ' . $ex->getMessage());
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int) $_POST['id'];
        db_run('UPDATE students SET status = 1 - status WHERE id=?', [$id]);
        $sid = db_val('SELECT user_id FROM students WHERE id=?', [$id]);
        if ($sid) db_run('UPDATE users SET status = (SELECT status FROM students WHERE id=?) WHERE id=?', [$id, $sid]);
        audit('Toggle student', 'Activated/deactivated student id ' . $id, $id);
        flash('success', 'Student status changed.');
    } elseif ($action === 'reset_pass') {
        $id = (int) $_POST['id'];
        $newpass = $_POST['newpass'] ?? '';
        if (strlen($newpass) >= 4) {
            $uid = db_val('SELECT user_id FROM students WHERE id=?', [$id]);
            db_run('UPDATE users SET password=? WHERE id=?', [password_hash($newpass, PASSWORD_DEFAULT), $uid]);
            audit('Reset student password', 'Student id ' . $id, $id);
            flash('success', 'Password reset successfully.');
        } else {
            flash('danger', 'Password must be at least 4 characters.');
        }
    }
    redirect(url('admin/students.php'));
}

// ---- Search & pagination ----
$q = trim($_GET['q'] ?? '');
$batch = (int) ($_GET['batch'] ?? 0);
$sem = (int) ($_GET['sem'] ?? 0);
$program = (int) ($_GET['program'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 15;

$where = ['1=1']; $params = [];
if ($q !== '') {
    $where[] = '(st.full_name LIKE ? OR st.student_code LIKE ? OR st.tu_reg_no LIKE ? OR st.exam_roll_no LIKE ?)';
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
}
if ($batch) { $where[] = 'st.batch_id = ?'; $params[] = $batch; }
if ($sem) { $where[] = 'st.current_semester_id = ?'; $params[] = $sem; }
if ($program) { $where[] = 'st.program_id = ?'; $params[] = $program; }
$whereSql = implode(' AND ', $where);

$countStmt = db()->prepare("SELECT COUNT(*) FROM students st WHERE $whereSql");
$p = paginate($countStmt, $params, $page, $per);

$rows = db_all(
    "SELECT st.*, b.name AS batch_name, s.name AS sem_name, sec.name AS section_name, p.name AS program_name
     FROM students st
     LEFT JOIN batches b ON b.id = st.batch_id
     LEFT JOIN semesters s ON s.id = st.current_semester_id
     LEFT JOIN sections sec ON sec.id = st.section_id
     LEFT JOIN programs p ON p.id = st.program_id
     WHERE $whereSql
     ORDER BY st.student_code
     LIMIT {$p['offset']}, {$per}",
    $params
);

$edit = !empty($_GET['edit']) ? db_one('SELECT * FROM students WHERE id=?', [(int) $_GET['edit']]) : null;
$batches = db_all('SELECT * FROM batches WHERE status=1 ORDER BY program_id, name');
$sems = db_all('SELECT * FROM semesters WHERE status=1 ORDER BY program_id, semester_no');
$sections = db_all('SELECT * FROM sections WHERE status=1 ORDER BY name');
$programs = db_all('SELECT * FROM programs WHERE status=1 ORDER BY name');
$editProgram = $edit && $edit['program_id'] ? (int) $edit['program_id'] : ($edit && $edit['batch_id'] ? (int) db_val('SELECT program_id FROM batches WHERE id=?', [$edit['batch_id']]) : 0);

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Students <span class="text-muted fs-6">(<?php echo $p['total']; ?>)</span></h4>
    <button class="btn btn-rms" data-bs-toggle="modal" data-bs-target="#studentModal"><i class="bi bi-person-plus me-1"></i> Add Student</button>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2" method="get" id="studentFilterForm">
            <div class="col-md-4">
                <input type="text" class="form-control" name="q" id="studentSearchBox" value="<?php echo e($q); ?>" placeholder="Search name, student ID, TU reg no, exam roll no" autocomplete="off">
            </div>
            <div class="col-md-2">
                <select class="form-select" name="program" onchange="this.form.submit()">
                    <option value="">All programs</option>
                    <?php foreach ($programs as $pr): ?><option value="<?php echo $pr['id']; ?>" <?php echo selected($program, $pr['id']); ?>><?php echo e($pr['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="batch">
                    <option value="">All batches</option>
                    <?php foreach ($batches as $b): ?><option value="<?php echo $b['id']; ?>" <?php echo selected($batch, $b['id']); ?>><?php echo e(program_name_of((int) $b['program_id']) . ' — ' . $b['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="sem">
                    <option value="">All semesters</option>
                    <?php foreach ($sems as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo selected($sem, $s['id']); ?>><?php echo e(program_name_of((int) $s['program_id']) . ' — ' . $s['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-rms flex-fill"><i class="bi bi-search"></i> Search</button>
                <a class="btn btn-outline-secondary" href="<?php echo e(url('admin/students.php')); ?>">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>ID</th><th>Student</th><th>Roll No</th><th>Program</th><th>Batch</th><th>Semester</th><th>Section</th><th>Contact</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><span class="badge text-bg-dark"><?php echo e($r['student_code']); ?></span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($r['photo']): ?><img src="<?php echo e(url($r['photo'])); ?>" class="rounded-circle" width="34" height="34" alt=""><?php endif; ?>
                            <div>
                                <a class="fw-semibold text-decoration-none" href="student_profile.php?id=<?php echo $r['id']; ?>"><?php echo e($r['full_name']); ?></a>
                                <div class="small text-muted"><?php echo e($r['tu_reg_no'] ?: '—'); ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?php echo e($r['exam_roll_no'] ?: '—'); ?></td>
                    <td><?php echo e($r['program_name'] ?? '—'); ?></td>
                    <td><?php echo e($r['batch_name'] ?? '—'); ?></td>
                    <td><?php echo e($r['sem_name'] ?? '—'); ?></td>
                    <td><?php echo e($r['section_name'] ?? '—'); ?></td>
                    <td class="small"><?php echo e($r['phone'] ?? '—'); ?><br><?php echo e($r['email'] ?? ''); ?></td>
                    <td><?php echo bool_badge($r['status']); ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-info" href="student_profile.php?id=<?php echo $r['id']; ?>"><i class="bi bi-person-badge"></i></a>
                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo $r['id']; ?>#modal"><i class="bi bi-pencil"></i></a>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete();">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-power"></i></button>
                        </form>
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#resetPassModal" onclick="document.getElementById('resetStudentId').value=<?php echo $r['id']; ?>" title="Reset password"><i class="bi bi-key"></i></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="10" class="text-center py-4 text-muted">No students found.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <?php if ($p['pages'] > 1): ?>
            <div class="card-footer"><?php echo $p['pagination']; ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- Reset password modal -->
<div class="modal fade" id="resetPassModal" tabindex="-1"><div class="modal-dialog modal-sm"><form class="modal-content" method="post">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="reset_pass"><input type="hidden" id="resetStudentId" name="id">
    <div class="modal-header"><h6 class="modal-title">Reset Student Password</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><label class="form-label">New Password</label>
        <input type="text" class="form-control" name="newpass" minlength="4" required></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-warning">Reset</button></div>
</form></div></div>

<!-- Add / Edit modal -->
<div class="modal fade" id="studentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="modal-header"><h5 class="modal-title"><?php echo $edit ? 'Edit Student' : 'Add Student'; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int) ($edit['id'] ?? 0); ?>">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Student ID *</label>
                        <input class="form-control" name="student_code" value="<?php echo e($edit['student_code'] ?? ''); ?>" placeholder="e.g. BCA24001" required></div>
                    <div class="col-md-6"><label class="form-label">Full Name *</label>
                        <input class="form-control" name="full_name" value="<?php echo e($edit['full_name'] ?? ''); ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Date of Birth (BS)</label>
                        <input type="text" class="form-control bs-date" name="dob" value="<?php echo e(bs_input_value($edit['dob'] ?? '')); ?>" placeholder="YYYY/MM/DD" maxlength="10"></div>
                    <div class="col-md-4"><label class="form-label">Gender</label>
                        <select class="form-select" name="gender">
                            <option value="">—</option>
                            <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                                <option value="<?php echo $g; ?>" <?php echo selected($edit['gender'] ?? '', $g); ?>><?php echo $g; ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-md-4"><label class="form-label">Admission Year</label>
                        <input type="number" class="form-control" name="admission_year" min="2000" max="2100" value="<?php echo e($edit['admission_year'] ?? ''); ?>"></div>
                    <div class="col-md-6"><label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo e($edit['email'] ?? ''); ?>"></div>
                    <div class="col-md-6"><label class="form-label">Phone</label>
                        <input class="form-control" name="phone" value="<?php echo e($edit['phone'] ?? ''); ?>"></div>
                    <div class="col-md-6"><label class="form-label">TU Registration No.</label>
                        <input class="form-control" name="tu_reg_no" value="<?php echo e($edit['tu_reg_no'] ?? ''); ?>"></div>
                    <div class="col-md-6"><label class="form-label">Exam Roll No.</label>
                        <input class="form-control" name="exam_roll_no" value="<?php echo e($edit['exam_roll_no'] ?? ''); ?>"></div>
                    <div class="col-12"><label class="form-label">Program *</label>
                        <select class="form-select program-select" name="program_id" required data-target="students">
                            <option value="">—</option>
                            <?php foreach ($programs as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo selected($editProgram, $p['id']); ?>><?php echo e($p['name']); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-12"><label class="form-label">Address</label>
                        <input class="form-control" name="address" value="<?php echo e($edit['address'] ?? ''); ?>"></div>
                    <div class="col-md-4"><label class="form-label">Batch</label>
                        <select class="form-select" name="batch_id" data-scope="students">
                            <option value="">—</option>
                            <?php foreach ($batches as $b): ?><option value="<?php echo $b['id']; ?>" data-program="<?php echo $b['program_id']; ?>" <?php echo selected($edit['batch_id'] ?? '', $b['id']); ?>><?php echo e(program_name_of((int) $b['program_id']) . ' — ' . $b['name']); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-md-4"><label class="form-label">Current Semester</label>
                        <select class="form-select" name="current_semester_id" data-scope="students">
                            <option value="">—</option>
                            <?php foreach ($sems as $s): ?><option value="<?php echo $s['id']; ?>" data-program="<?php echo $s['program_id']; ?>" <?php echo selected($edit['current_semester_id'] ?? '', $s['id']); ?>><?php echo e(program_name_of((int) $s['program_id']) . ' — ' . $s['name']); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-md-4"><label class="form-label">Section</label>
                        <select class="form-select" name="section_id">
                            <option value="">—</option>
                            <?php foreach ($sections as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo selected($edit['section_id'] ?? '', $c['id']); ?>><?php echo e($c['name']); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-md-8"><label class="form-label">Profile Photo</label>
                        <input type="file" class="form-control" name="photo" accept="image/*">
                        <?php if ($edit && $edit['photo']): ?><small class="text-muted">Current: <?php echo e(basename($edit['photo'])); ?></small><?php endif; ?></div>
                    <?php if (!$edit): ?>
                    <div class="col-md-4"><label class="form-label">Default Password *</label>
                        <input type="text" class="form-control" name="password" value="student123" minlength="4" required></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-rms">Save</button></div>
        </form>
    </div>
</div>
<?php if ($edit && !empty($_GET['edit'])): ?><script>document.addEventListener('DOMContentLoaded', function () { var m = document.getElementById('studentModal'); if (m && window.bootstrap) new bootstrap.Modal(m).show(); });</script><?php endif; ?>
<script>
(function () {
    var box = document.getElementById('studentSearchBox');
    var form = document.getElementById('studentFilterForm');
    if (!box || !form) return;
    var timer = null;
    box.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { form.submit(); }, 450);
    });
})();
</script>
<?php echo program_scope_script(); ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>