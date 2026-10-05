<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('examinations');
$title = 'Examinations';
$active = 'exams';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $data = [
            'name' => trim($_POST['name']),
            'exam_type_id' => (int) ($_POST['exam_type_id'] ?? 0) ?: null,
            'program_id' => (int) ($_POST['program_id'] ?? 0),
            'academic_year_id' => (int) ($_POST['academic_year_id'] ?? 0) ?: null,
            'batch_id' => (int) ($_POST['batch_id'] ?? 0) ?: null,
            'semester_id' => (int) ($_POST['semester_id'] ?? 0),
            'exam_date' => bs_parse_input($_POST['exam_date'] ?? ''),
        ];
        if ($data['exam_date'] === false) {
            flash('danger', 'Exam date must be a valid Bikram Sambat date (format YYYY/MM/DD).');
        } elseif ($data['name'] === '' || $data['program_id'] <= 0 || $data['semester_id'] <= 0) {
            flash('danger', 'Exam name, program and semester are required.');
        } elseif (!db_val('SELECT COUNT(*) FROM programs WHERE id = ? AND status = 1', [$data['program_id']])) {
            flash('danger', 'Selected program is invalid.');
        } elseif (!db_val('SELECT COUNT(*) FROM semesters WHERE id = ? AND program_id = ?', [$data['semester_id'], $data['program_id']])) {
            flash('danger', 'Selected semester does not belong to the program.');
        } elseif ($data['batch_id'] && !db_val('SELECT COUNT(*) FROM batches WHERE id = ? AND program_id = ?', [$data['batch_id'], $data['program_id']])) {
            flash('danger', 'Selected batch does not belong to the program.');
        } else {
            try {
                if ($id > 0) {
                    db_run('UPDATE exams SET name=?, exam_type_id=?, program_id=?, academic_year_id=?, batch_id=?, semester_id=?, exam_date=? WHERE id=?',
                        [$data['name'], $data['exam_type_id'], $data['program_id'], $data['academic_year_id'], $data['batch_id'], $data['semester_id'], $data['exam_date'], $id]);
                    audit('Edit exam', 'Updated exam: ' . $data['name'], $id);
                    flash('success', 'Exam updated.');
                } else {
                    db_run('INSERT INTO exams (name, exam_type_id, program_id, academic_year_id, batch_id, semester_id, exam_date) VALUES (?,?,?,?,?,?,?)',
                        [$data['name'], $data['exam_type_id'], $data['program_id'], $data['academic_year_id'], $data['batch_id'], $data['semester_id'], $data['exam_date']]);
                    $examId = last_id();
                    // Auto-add all active subjects of the program + semester with teacher from assignments
                    $fullMarks = $data['exam_type_id'] && exam_type_code($data['exam_type_id']) === 'final' ? 100 : 20;
                    $subjects = db_all('SELECT id FROM subjects WHERE program_id = ? AND semester_id = ? AND status = 1', [$data['program_id'], $data['semester_id']]);
                    $added = 0;
                    foreach ($subjects as $s) {
                        $teacherId = db_val('SELECT teacher_id FROM teacher_subjects WHERE subject_id = ? AND status = 1 ORDER BY id DESC LIMIT 1', [$s['id']]);
                        db_run('INSERT IGNORE INTO exam_subjects (exam_id, subject_id, teacher_id, full_marks) VALUES (?,?,?,?)',
                            [$examId, $s['id'], $teacherId ? (int) $teacherId : null, $fullMarks]);
                        $added++;
                    }
                    audit('Add exam', 'Created exam: ' . $data['name'] . ' (' . $added . ' subjects auto-added)', $examId);
                    flash('success', 'Exam created with ' . $added . ' subject(s). Now assign teachers and start marks entry.');
                    redirect(url('admin/exam_setup.php?exam_id=' . $examId));
                }
            } catch (PDOException $ex) {
                flash('danger', 'Database error: ' . $ex->getMessage());
            }
        }
    } elseif ($action === 'toggle') {
        db_run('UPDATE exams SET status = 1 - status WHERE id=?', [(int) $_POST['id']]);
        flash('success', 'Status changed.');
    } elseif ($action === 'delete') {
        $id = (int) $_POST['id'];
        db_run('DELETE FROM exams WHERE id=?', [$id]);
        audit('Delete exam', 'Deleted exam id ' . $id, $id);
        flash('success', 'Exam deleted.');
    }
    redirect(url('admin/exams.php'));
}

$edit = !empty($_GET['edit']) ? db_one('SELECT * FROM exams WHERE id=?', [(int) $_GET['edit']]) : null;
$programFilter = (int) ($_GET['program_id'] ?? 0);
$rows = db_all('SELECT e.*, p.name AS program_name, et.name AS type_name, a.name AS ac_year, b.name AS batch_name, s.name AS sem_name
                FROM exams e
                LEFT JOIN programs p ON p.id = e.program_id
                LEFT JOIN exam_types et ON et.id = e.exam_type_id
                LEFT JOIN academic_years a ON a.id = e.academic_year_id
                LEFT JOIN batches b ON b.id = e.batch_id
                JOIN semesters s ON s.id = e.semester_id
                WHERE (? = 0 OR e.program_id = ?)
                ORDER BY e.id DESC', [$programFilter, $programFilter]);
$types = exam_types();
$yearList = db_all('SELECT * FROM academic_years ORDER BY start_date DESC');
$programs = db_all('SELECT * FROM programs WHERE status=1 ORDER BY name');
$batches = db_all('SELECT * FROM batches WHERE status=1 ORDER BY program_id, name');
$sems = db_all('SELECT * FROM semesters WHERE status=1 ORDER BY program_id, semester_no');

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-patch-check me-2"></i>Examinations</h4>
    <div class="d-flex gap-2">
        <form method="get" class="d-flex gap-2">
            <select class="form-select form-select-sm" name="program_id" onchange="this.form.submit()">
                <option value="0">All programs</option>
                <?php foreach ($programs as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo selected($programFilter, $p['id']); ?>><?php echo e($p['name']); ?></option><?php endforeach; ?>
            </select>
        </form>
        <button class="btn btn-rms" data-bs-toggle="modal" data-bs-target="#examModal"><i class="bi bi-plus-lg me-1"></i> Create Exam</button>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Exam</th><th>Type</th><th>Program</th><th>Semester</th><th>Batch</th><th>Academic Year</th><th>Exam Date</th><th>Lifecycle</th><th>Active</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $r): $ls = exam_display_status($r); ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td class="fw-semibold"><a class="text-decoration-none" href="exam_setup.php?exam_id=<?php echo $r['id']; ?>"><?php echo e($r['name']); ?></a></td>
                    <td><?php echo e($r['type_name'] ?? '—'); ?></td>
                    <td><?php echo e($r['program_name'] ?? '—'); ?></td>
                    <td><?php echo e($r['sem_name']); ?></td>
                    <td><?php echo e($r['batch_name'] ?? '—'); ?></td>
                    <td><?php echo e($r['ac_year'] ?? '—'); ?></td>
                    <td class="small"><?php echo fdate($r['exam_date']); ?></td>
                    <td><span class="badge <?php echo $ls['class']; ?>"><?php echo e($ls['label']); ?></span></td>
                    <td><?php echo bool_badge($r['status']); ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-info" href="exam_setup.php?exam_id=<?php echo $r['id']; ?>">Setup</a>
                        <a class="btn btn-sm btn-outline-success" href="results.php?exam_id=<?php echo $r['id']; ?>"><?php echo exam_is_terminal($r) ? 'Marks' : 'Results'; ?></a>
                        <a class="btn btn-sm btn-outline-secondary" href="?edit=<?php echo $r['id']; ?>#modal"><i class="bi bi-pencil"></i></a>
                        <form class="d-inline" method="post" onsubmit="return confirmDelete('Delete this exam? All its marks and results will be removed.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="11" class="text-center py-4 text-muted">No examinations yet. Create the BCA 4th Semester 1st Terminal Examination.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="examModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <?php echo csrf_field(); ?>
            <div class="modal-header"><h5 class="modal-title"><?php echo $edit ? 'Edit Exam' : 'Create Exam'; ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int) ($edit['id'] ?? 0); ?>">
                <div class="mb-3"><label class="form-label">Exam Name *</label>
                    <input class="form-control" name="name" value="<?php echo e($edit['name'] ?? ''); ?>" placeholder="BCA 4th Semester 1st Terminal Examination" required></div>
                <div class="row g-2 mb-3">
                    <div class="col-6"><label class="form-label">Program *</label>
                        <select class="form-select program-select" name="program_id" required data-target="exams">
                            <option value="">— Program —</option>
                            <?php foreach ($programs as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo selected($edit['program_id'] ?? '', $p['id']); ?>><?php echo e($p['name']); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-6"><label class="form-label">Exam Type *</label>
                        <select class="form-select" name="exam_type_id" required>
                            <option value="">—</option>
                            <?php foreach ($types as $t): ?><option value="<?php echo $t['id']; ?>" <?php echo selected($edit['exam_type_id'] ?? '', $t['id']); ?>><?php echo e($t['name']); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-6"><label class="form-label">Semester *</label>
                        <select class="form-select" name="semester_id" required data-scope="exams">
                            <option value="">—</option>
                            <?php foreach ($sems as $s): ?><option value="<?php echo $s['id']; ?>" data-program="<?php echo $s['program_id']; ?>" <?php echo selected($edit['semester_id'] ?? '', $s['id']); ?>><?php echo e(program_name_of((int) $s['program_id']) . ' — ' . $s['name']); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-6"><label class="form-label">Batch</label>
                        <select class="form-select" name="batch_id" data-scope="exams">
                            <option value="">—</option>
                            <?php foreach ($batches as $b): ?><option value="<?php echo $b['id']; ?>" data-program="<?php echo $b['program_id']; ?>" <?php echo selected($edit['batch_id'] ?? '', $b['id']); ?>><?php echo e(program_name_of((int) $b['program_id']) . ' — ' . $b['name']); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-6"><label class="form-label">Academic Year</label>
                        <select class="form-select" name="academic_year_id">
                            <option value="">—</option>
                            <?php foreach ($yearList as $y): ?><option value="<?php echo $y['id']; ?>" <?php echo selected($edit['academic_year_id'] ?? '', $y['id']); ?>><?php echo e($y['name']); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-6"><label class="form-label">Exam Date (BS)</label>
                        <input type="text" class="form-control bs-date" name="exam_date" value="<?php echo e(bs_input_value($edit['exam_date'] ?? '')); ?>" placeholder="YYYY/MM/DD" maxlength="10"></div>
                </div>
                <?php if (!$edit): ?>
                <div class="alert alert-light border small mb-0"><i class="bi bi-info-circle"></i>
                    On creation, all active subjects of the selected program and semester are added to the exam automatically with their assigned teachers.
                    Terminal subjects default to <strong>full marks 20</strong>, Final Examination subjects default to <strong>full marks 100</strong>.</div>
                <?php endif; ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-rms"><?php echo $edit ? 'Save' : 'Create Exam'; ?></button></div>
        </form>
    </div>
</div>
<?php if ($edit && !empty($_GET['edit'])): ?><script>document.addEventListener('DOMContentLoaded', function () { var m = document.getElementById('examModal'); if (m && window.bootstrap) new bootstrap.Modal(m).show(); });</script><?php endif; ?>
<?php echo program_scope_script(); ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>