<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/academics.php';
require_once __DIR__ . '/../includes/result_engine.php';
$user = require_role(ROLE_STUDENT);
$title = 'Student Dashboard';
$active = 'dashboard';

$studentId = (int) db_val('SELECT student_id FROM users WHERE id = ?', [$user['id']]);
$student = db_one(
    "SELECT st.*, b.name AS batch_name, s.name AS sem_name, sec.name AS section_name, p.name AS program_name
     FROM students st
     LEFT JOIN batches b ON b.id = st.batch_id
     LEFT JOIN semesters s ON s.id = st.current_semester_id
     LEFT JOIN sections sec ON sec.id = st.section_id
     LEFT JOIN programs p ON p.id = st.program_id
     WHERE st.id = ?", [$studentId]);
if (!$student) { flash('danger', 'Student profile not found.'); redirect(url('auth/logout.php')); }

$subjectCount = (int) db_val('SELECT COUNT(*) FROM student_subjects WHERE student_id = ? AND status = 1', [$studentId]);

// latest published exam (terminal or final) for this student
$latest = db_one(
    "SELECT DISTINCT e.id, e.name exam_name, e.published_at, et.code type_code, et.name type_name, sm.name semester_name
     FROM exams e
     JOIN exam_types et ON et.id = e.exam_type_id
     JOIN exam_subjects es ON es.exam_id = e.id AND es.status = 1
     JOIN student_subjects ss ON ss.subject_id = es.subject_id AND ss.status = 1
     JOIN semesters sm ON sm.id = e.semester_id
     JOIN students st ON st.id = ss.student_id
     WHERE e.exam_status = 'published' AND ss.student_id = ? AND st.program_id = e.program_id
     ORDER BY e.published_at DESC, e.id DESC LIMIT 1", [$studentId]);

// latest published final result (for SGPA display)
$published = db_one(
    "SELECT r.*, e.name exam_name, sm.name semester_name
     FROM results r JOIN exams e ON e.id = r.exam_id JOIN semesters sm ON sm.id = r.semester_id
     WHERE r.student_id = ? AND r.is_locked = 1 ORDER BY sm.semester_no DESC LIMIT 1", [$studentId]);
$latestSgpa = $published ? $published['sgpa'] : null;
$backs = student_back_subjects($studentId);
$cumul = cumulative_gpa(student_semesters_summary($studentId, 'published'));
$notices = db_all('SELECT * FROM notices WHERE status=1 ORDER BY notice_date DESC, id DESC LIMIT 3');
if ($latest) {
    $isTerminal = in_array($latest['type_code'], ['first_terminal', 'second_terminal', 'third_terminal'], true);
    $terminalPreview = $isTerminal ? terminal_student_summary((int) $latest['id'], $studentId) : null;
    $resultPreview = !$isTerminal ? db_one('SELECT * FROM results WHERE student_id = ? AND exam_id = ?', [$studentId, $latest['id']]) : null;
} else {
    $isTerminal = false; $terminalPreview = null; $resultPreview = null;
}

include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card bg-rms-primary text-white">
            <div class="card-body d-flex align-items-center gap-3">
                <?php if ($student['photo']): ?>
                    <img src="<?php echo e(url($student['photo'])); ?>" class="rounded-circle border border-light" width="82" height="82" alt="">
                <?php else: ?>
                    <div class="avatar-circle bg-warning text-dark" style="width:82px;height:82px;font-size:2rem;"><?php echo e(strtoupper(substr($student['full_name'], 0, 1))); ?></div>
                <?php endif; ?>
                <div>
                    <h4 class="mb-1"><?php echo e($student['full_name']); ?></h4>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge text-bg-light"><?php echo e($student['student_code']); ?></span>
                        <span class="badge text-bg-light"><?php echo e($student['program_name'] ?? 'BCA'); ?> — <?php echo e($student['sem_name'] ?? '—'); ?></span>
                        <span class="badge text-bg-light"><?php echo e($student['batch_name'] ?? '—'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column justify-content-center text-center">
                <div class="stat-title">Latest SGPA</div>
                <div class="display-6 fw-bold text-rms-primary"><?php echo $latestSgpa !== null ? fmt_gpa($latestSgpa, (int) get_setting('sgpa_decimals', 2)) : '—'; ?></div>
                <div class="small text-muted">CGPA: <?php echo $cumul['total_credits'] ? fmt_gpa($cumul['cgpa']) : '—'; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-2"><div class="card stat-card"><div class="card-body"><div class="stat-title">Subjects</div><div class="stat-value"><?php echo $subjectCount; ?></div></div></div></div>
    <div class="col-6 col-md-2"><div class="card stat-card" style="border-left-color:#16a34a"><div class="card-body"><div class="stat-title">Result Status</div>
        <div class="stat-value"><?php echo $latest ? status_badge($isTerminal ? $terminalPreview['overall'] ?? 'Pending' : ($resultPreview['result_status'] ?? 'Pending')) : '<i class="bi bi-dash-lg fs-4"></i>'; ?></div></div></div></div>
    <div class="col-6 col-md-2"><div class="card stat-card" style="border-left-color:#dc2626"><div class="card-body"><div class="stat-title">Back Subjects</div><div class="stat-value text-danger"><?php echo count($backs); ?></div></div></div></div>
    <div class="col-6 col-md-2"><div class="card stat-card" style="border-left-color:#2563eb"><div class="card-body"><div class="stat-title">Semester</div><div class="stat-value"><?php echo e($student['sem_name'] ?? '—'); ?></div></div></div></div>
    <div class="col-6 col-md-2"><div class="card stat-card" style="border-left-color:#7c3aed"><div class="card-body"><div class="stat-title">Batch</div><div class="stat-value fs-5 pt-2"><?php echo e($student['batch_name'] ?? '—'); ?></div></div></div></div>
    <div class="col-6 col-md-2"><div class="card stat-card" style="border-left-color:#0891b2"><div class="card-body"><div class="stat-title">Section</div><div class="stat-value pt-1"><?php echo e($student['section_name'] ?? '—'); ?></div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">Quick Actions</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a class="btn btn-rms" href="result.php"><i class="bi bi-file-earmark-text me-1"></i> View Result</a>
                <a class="btn btn-outline-primary" href="history.php"><i class="bi bi-book-half me-1"></i> Academic History</a>
                <a class="btn btn-outline-danger" href="<?php echo $latest ? e(url('pdf/marksheet.php?exam_id=' . $latest['id'] . '&student_id=' . $studentId)) : '#'; ?>" <?php echo $latest ? '' : 'disabled'; ?>><i class="bi bi-file-pdf me-1"></i> Download Marksheet</a>
                <a class="btn btn-outline-secondary" href="profile.php"><i class="bi bi-person-circle me-1"></i> Profile</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Latest Result — <?php echo $latest ? e($latest['exam_name']) : 'Not published yet'; ?></div>
            <div class="card-body">
                <?php if ($latest && $isTerminal): ?>
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light"><tr><th>Code</th><th>Subject</th><th class="text-center">Credit</th><th class="text-center">Full</th><th class="text-center">Obtained</th><th class="text-center">Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($terminalPreview['subjects'] as $d): ?>
                        <tr>
                            <td><?php echo e($d['subject_code']); ?></td>
                            <td><?php echo e($d['subject_name']); ?></td>
                            <td class="text-center"><?php echo e($d['credit_hours']); ?></td>
                            <td class="text-center"><?php echo marks_round($d['full_marks']); ?></td>
                            <td class="text-center fw-semibold"><?php echo marks_round($d['obtained']); ?></td>
                            <td class="text-center"><?php echo status_badge($d['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light"><tr>
                        <th colspan="2">Overall: <?php echo status_badge($terminalPreview['overall']); ?></th>
                        <th colspan="2"><?php echo marks_round($terminalPreview['full']); ?></th>
                        <th><?php echo marks_round($terminalPreview['total']); ?></th>
                        <th><?php echo round($terminalPreview['percentage'], 1); ?>%</th>
                    </tr></tfoot>
                </table>
                <?php elseif ($latest && $resultPreview):
                    $rows = result_subject_rows((int) $resultPreview['id']); ?>
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light"><tr><th>Code</th><th>Subject</th><th class="text-center">Credit</th><th class="text-center">Total</th><th class="text-center">Grade</th><th class="text-center">GP</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $d): ?>
                        <tr>
                            <td><?php echo e($d['subject_code']); ?></td>
                            <td><?php echo e($d['subject_name']); ?></td>
                            <td class="text-center"><?php echo e($d['credit_hours']); ?></td>
                            <td class="text-center fw-semibold"><?php echo marks_round($d['total_marks']); ?>/<?php echo e((int) $d['max_marks']); ?></td>
                            <td class="text-center"><?php echo e($d['grade']); ?></td>
                            <td class="text-center"><?php echo e($d['grade_point']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light"><tr>
                        <th colspan="2">SGPA: <?php echo fmt_gpa($resultPreview['sgpa']); ?></th>
                        <th><?php echo e($resultPreview['total_credits']); ?></th>
                        <th colspan="3" class="text-center"><?php echo status_badge($resultPreview['result_status']); ?></th>
                    </tr></tfoot>
                </table>
                <?php else: ?>
                    <div class="alert alert-info mb-0"><i class="bi bi-hourglass-split"></i> Your result for this semester is not published yet. Check back later or see <a href="notices.php">notices</a>.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Back Subjects</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($backs as $b): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i><?php echo e($b['name']); ?></span>
                            <span class="badge text-bg-light"><?php echo e($b['semester_name']); ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$backs): ?>
                        <li class="list-group-item text-success"><i class="bi bi-check-circle me-2"></i>No back subjects</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between"><span>Recent Notices</span><a class="small" href="notices.php">All</a></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($notices as $n): ?>
                        <li class="list-group-item py-2">
                            <div class="fw-semibold small"><?php echo e($n['title']); ?></div>
                            <small class="text-muted"><?php echo fdate($n['notice_date']); ?></small>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$notices): ?><li class="list-group-item text-muted">No notices.</li><?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>