<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/result_engine.php';
require_once __DIR__ . '/../includes/academics.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('dashboard');

$title = 'Admin Dashboard';
$active = 'dashboard';

$stats = [
    'students'     => (int) db_val('SELECT COUNT(*) FROM students WHERE status = 1'),
    'teachers'     => (int) db_val('SELECT COUNT(*) FROM teachers WHERE status = 1'),
    'subjects'     => (int) db_val('SELECT COUNT(*) FROM subjects WHERE status = 1'),
    'batches'      => (int) db_val('SELECT COUNT(*) FROM batches WHERE status = 1'),
    'semesters'    => (int) db_val('SELECT COUNT(*) FROM semesters WHERE status = 1'),
    'pending_marks'=> (int) db_val('SELECT COUNT(*) FROM marks WHERE submitted = 0'),
    'submitted'    => (int) db_val('SELECT COUNT(*) FROM marks WHERE submitted = 1'),
    'published'    => (int) db_val("SELECT COUNT(*) FROM exams WHERE exam_status = 'published'"),
    'pending_exams'=> (int) db_val("SELECT COUNT(*) FROM exams WHERE exam_status IN ('draft','marks_entry') AND status = 1"),
];

// Latest exam (terminal or final)
$latestExam = db_one('SELECT e.*, s.name AS sem_name, et.name AS type_name FROM exams e
    JOIN semesters s ON s.id = e.semester_id JOIN exam_types et ON et.id = e.exam_type_id
    ORDER BY e.id DESC LIMIT 1');
$isTerminal = $latestExam ? exam_is_terminal($latestExam) : false;

$examStats = ['total' => 0, 'pass' => 0, 'fail' => 0, 'absent' => 0, 'pending' => 0, 'sgpa' => 0];
if ($latestExam) {
    if ($isTerminal) {
        $entries = [];
        foreach (exam_students((int) $latestExam['id']) as $st) {
            $sum = terminal_student_summary((int) $latestExam['id'], (int) $st['id']);
            if ($sum && $sum['subjects']) $entries[] = $sum;
        }
        $examStats['total'] = count($entries);
        foreach ($entries as $e) {
            if ($e['overall'] === 'Pass') $examStats['pass']++;
            elseif ($e['overall'] === 'Absent') $examStats['absent']++;
            elseif ($e['overall'] === 'Fail') $examStats['fail']++;
            else $examStats['pending']++;
        }
    } else {
        $examStats['total'] = (int) db_val('SELECT COUNT(*) FROM results WHERE exam_id = ?', [(int) $latestExam['id']]);
        $examStats['pass']   = (int) db_val("SELECT COUNT(*) FROM results WHERE exam_id = ? AND result_status = 'Pass'", [(int) $latestExam['id']]);
        $examStats['fail']   = (int) db_val("SELECT COUNT(*) FROM results WHERE exam_id = ? AND result_status = 'Fail'", [(int) $latestExam['id']]);
        $examStats['absent'] = (int) db_val("SELECT COUNT(*) FROM results WHERE exam_id = ? AND result_status = 'Absent'", [(int) $latestExam['id']]);
        $examStats['pending'] = $examStats['total'] - $examStats['pass'] - $examStats['fail'] - $examStats['absent'];
        $examStats['sgpa'] = db_val('SELECT ROUND(AVG(sgpa),2) FROM results WHERE exam_id = ?', [(int) $latestExam['id']]) ?? 0;
    }
}

$gradeDist = [];
$gradeTotal = 0;
if ($latestExam) {
    if ($isTerminal) {
        $gradeDist = db_all(
            "SELECT m.status AS grade, COUNT(*) AS cnt
             FROM marks m JOIN exam_subjects es ON es.id = m.exam_subject_id
             WHERE es.exam_id = ? GROUP BY m.status ORDER BY m.status", [(int) $latestExam['id']]);
        $gradeTotal = array_sum(array_column($gradeDist, 'cnt'));
    } else {
        $gradeDist = db_all(
            "SELECT rd.grade AS grade, COUNT(*) AS cnt
             FROM results r JOIN result_details rd ON rd.result_id = r.id
             WHERE r.exam_id = ? AND rd.subject_status = 'Pass'
             GROUP BY rd.grade ORDER BY rd.grade", [(int) $latestExam['id']]);
        $gradeTotal = array_sum(array_column($gradeDist, 'cnt'));
    }
}
include __DIR__ . '/../includes/header.php';
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-person-lines-fill stat-icon text-primary"></i><div><div class="stat-title">Students</div><div class="stat-value"><?php echo $stats['students']; ?></div></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#0d9488"><div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-person-video3 stat-icon text-success"></i><div><div class="stat-title">Teachers</div><div class="stat-value"><?php echo $stats['teachers']; ?></div></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#d97706"><div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-book stat-icon text-warning"></i><div><div class="stat-title">Subjects</div><div class="stat-value"><?php echo $stats['subjects']; ?></div></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#dc2626"><div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-people-fill stat-icon text-danger"></i><div><div class="stat-title">Batches</div><div class="stat-value"><?php echo $stats['batches']; ?></div></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#7c3aed"><div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-layers stat-icon text-purple"></i><div><div class="stat-title">Semesters</div><div class="stat-value"><?php echo $stats['semesters']; ?></div></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#2563eb"><div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-pencil-square stat-icon text-primary"></i><div><div class="stat-title">Marks Not Submitted</div><div class="stat-value"><?php echo $stats['pending_marks']; ?></div></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#0891b2"><div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-clipboard-check stat-icon text-info"></i><div><div class="stat-title">Submitted Marks</div><div class="stat-value"><?php echo $stats['submitted']; ?></div></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#16a34a"><div class="card-body d-flex align-items-center gap-3">
        <i class="bi bi-lock-fill stat-icon text-success"></i><div><div class="stat-title">Published Exams</div><div class="stat-value"><?php echo $stats['published']; ?></div></div>
    </div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Latest Examination Performance</span>
                <?php if ($latestExam): ?><span class="badge text-bg-light"><?php echo e($latestExam['name'] . ' (' . $latestExam['type_name'] . ')'); ?></span><?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$latestExam): ?>
                    <p class="text-muted mb-0">No examination created yet. <a href="<?php echo e(url('admin/exams.php')); ?>">Create one</a> to see performance stats.</p>
                <?php elseif ($latestExam['exam_status'] === 'draft' || $latestExam['exam_status'] === 'marks_entry'): ?>
                    <p class="text-muted mb-0"><i class="bi bi-hourglass-split"></i> <strong><?php echo e($latestExam['name']); ?></strong> is in
                        <span class="badge"><?php echo exam_display_status($latestExam['exam_status']); ?></span>.
                        Marks are still being entered. Stats appear once marks are submitted.</p>
                    <a class="btn btn-sm btn-rms mt-3" href="<?php echo e(url('admin/results.php?exam_id=' . $latestExam['id'])); ?>">Open Result Processing</a>
                <?php else: ?>
                <div class="row text-center g-3 mb-3">
                    <div class="col-4"><div class="border rounded py-3"><div class="fs-3 fw-bold"><?php echo $examStats['total']; ?></div><div class="text-muted small">Total</div></div></div>
                    <div class="col-4"><div class="border rounded py-3 bg-success-subtle"><div class="fs-3 fw-bold text-success"><?php echo $examStats['pass']; ?></div><div class="text-muted small">Passed</div></div></div>
                    <div class="col-4"><div class="border rounded py-3 bg-danger-subtle"><div class="fs-3 fw-bold text-danger"><?php echo $examStats['fail']; ?></div><div class="text-muted small">Failed</div></div></div>
                    <div class="col-4"><div class="border rounded py-3 bg-warning-subtle"><div class="fs-3 fw-bold text-warning"><?php echo $examStats['absent']; ?></div><div class="text-muted small">Absent</div></div></div>
                    <div class="col-4"><div class="border rounded py-3"><div class="fs-3 fw-bold"><?php echo $examStats['pending']; ?></div><div class="text-muted small">Pending / Other</div></div></div>
                    <div class="col-4"><div class="border rounded py-3"><div class="fs-3 fw-bold"><?php echo $isTerminal ? '—' : fmt_gpa($examStats['sgpa']); ?></div><div class="text-muted small">Avg. SGPA</div></div></div>
                </div>
                <a class="btn btn-sm btn-rms" href="<?php echo e(url('admin/results.php?exam_id=' . $latestExam['id'])); ?>">Open Result Processing</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><?php echo $isTerminal && $latestExam ? 'Status Distribution — ' . e($latestExam['name']) : ($latestExam ? 'Grade Distribution — ' . e($latestExam['name']) : 'Grade Distribution'); ?></div>
            <div class="card-body">
                <?php if ($gradeTotal === 0): ?>
                    <p class="text-muted mb-0">No marks / computed results yet.</p>
                <?php else: foreach ($gradeDist as $g): $pct = round(($g['cnt'] / $gradeTotal) * 100, 1); ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge text-bg-primary" style="width:70px"><?php echo e($g['grade']); ?></span>
                        <div class="progress flex-grow-1" style="height:12px">
                            <div class="progress-bar" role="progressbar" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                        <span class="small text-muted" style="width:80px"><?php echo $g['cnt']; ?> (<?php echo $pct; ?>%)</span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Quick Actions</div>
            <div class="card-body d-grid gap-2">
                <a class="btn btn-rms" href="<?php echo e(url('admin/results.php')); ?>"><i class="bi bi-calculator me-1"></i> Manage Exams / Results</a>
                <a class="btn btn-outline-primary" href="<?php echo e(url('admin/students.php')); ?>"><i class="bi bi-plus-circle me-1"></i> Add Student</a>
                <a class="btn btn-outline-primary" href="<?php echo e(url('admin/teachers.php')); ?>"><i class="bi bi-plus-circle me-1"></i> Add Teacher</a>
                <a class="btn btn-outline-primary" href="<?php echo e(url('admin/exams.php')); ?>"><i class="bi bi-plus-circle me-1"></i> Manage Examinations</a>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>