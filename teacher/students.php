<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/academics.php';
$user = require_role(ROLE_TEACHER);
$title = 'My Students';
$active = 'students';

$teacherId = (int) db_val('SELECT teacher_id FROM users WHERE id = ?', [$user['id']]);
$subjectId = (int) ($_GET['subject'] ?? 0);

$mySubjects = db_all(
    "SELECT tsu.subject_id, su.code, su.name AS subject_name, su.semester_id, sm.name AS semester_name
     FROM teacher_subjects tsu JOIN subjects su ON su.id = tsu.subject_id AND su.status=1
     JOIN semesters sm ON sm.id = su.semester_id
     WHERE tsu.teacher_id = ? AND tsu.status = 1 ORDER BY su.code", [$teacherId]);
if (!$subjectId && $mySubjects) $subjectId = (int) $mySubjects[0]['subject_id'];

$students = $subjectId
    ? db_all(
        "SELECT st.id, st.student_code, st.exam_roll_no, st.full_name, st.email, st.phone,
                b.name AS batch_name, sec.name AS section_name
         FROM student_subjects ss JOIN students st ON st.id = ss.student_id AND st.status=1
         LEFT JOIN batches b ON b.id = st.batch_id LEFT JOIN sections sec ON sec.id = st.section_id
         JOIN subjects su ON su.id = ss.subject_id
         WHERE ss.subject_id = ? AND ss.status = 1 AND st.program_id = su.program_id ORDER BY st.student_code", [$subjectId])
    : [];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>My Students</h4>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-6"><select class="form-select" name="subject" onchange="this.form.submit()">
        <?php foreach ($mySubjects as $s): ?><option value="<?php echo $s['subject_id']; ?>" <?php echo selected($subjectId, $s['subject_id']); ?>><?php echo e($s['code'] . ' — ' . $s['subject_name'] . ' (' . $s['semester_name'] . ')'); ?></option><?php endforeach; ?>
    </select></div>
</form>

<div class="card">
    <div class="card-header">Students — <?php echo e(db_val('SELECT name FROM subjects WHERE id=?', [$subjectId])); ?> (<?php echo count($students); ?>)</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Roll No.</th><th>Student</th><th>Batch</th><th>Section</th><th>Contact</th></tr></thead>
            <tbody>
            <?php foreach ($students as $st): ?>
                <tr>
                    <td><span class="badge text-bg-dark"><?php echo e($st['student_code']); ?></span></td>
                    <td class="fw-semibold"><?php echo e($st['full_name']); ?></td>
                    <td><?php echo e($st['batch_name'] ?? '—'); ?></td>
                    <td><?php echo e($st['section_name'] ?? '—'); ?></td>
                    <td class="small"><?php echo e($st['email'] ?? $st['phone'] ?? '—'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$students): ?><tr><td colspan="5" class="text-center py-4 text-muted">No students for this subject.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>