<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/result_engine.php';
require_once __DIR__ . '/../includes/academics.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('analytics');
$title = 'Analytics';
$active = 'analytics';

$programs = db_all('SELECT * FROM programs WHERE status=1 ORDER BY name');
$program = (int) ($_GET['program'] ?? 0);
$exams = db_all('SELECT e.*, s.name AS sem_name, et.name AS type_name, p.name AS program_name FROM exams e
    JOIN semesters s ON s.id = e.semester_id JOIN exam_types et ON et.id = e.exam_type_id
    LEFT JOIN programs p ON p.id = e.program_id
    WHERE (? = 0 OR e.program_id = ?) ORDER BY e.id DESC', [$program, $program]);
$examId = (int) ($_GET['exam_id'] ?? ($exams[0]['id'] ?? 0));
$exam = $examId ? db_one('SELECT * FROM exams WHERE id=?', [$examId]) : null;
$isTerminal = $exam ? exam_is_terminal($exam) : false;

$summary = ['total' => 0, 'pass' => 0, 'fail' => 0, 'absent' => 0, 'pending' => 0];
$gradeDist = [];
$subjectPerf = [];
$avgPassPct = 0;
$avgMarks = 0;

if ($examId) {
    if ($isTerminal) {
        $entries = [];
        foreach (exam_students($examId) as $st) {
            $sum = terminal_student_summary($examId, (int) $st['id']);
            if ($sum && $sum['subjects']) $entries[] = $sum;
        }
        $summary['total'] = count($entries);
        foreach ($entries as $e) {
            if ($e['overall'] === 'Pass') $summary['pass']++;
            elseif ($e['overall'] === 'Absent') $summary['absent']++;
            elseif ($e['overall'] === 'Fail') $summary['fail']++;
            else $summary['pending']++;
        }
        $avgPassPct = ($summary['pass'] + $summary['fail']) > 0 ? round(($summary['pass'] / ($summary['pass'] + $summary['fail'])) * 100, 1) : 0;
        $avgMarks = $summary['total'] ? round(array_sum(array_map(fn($e) => $e['percentage'], $entries)) / $summary['total'], 2) : 0;

        $gradeDist = db_all(
            "SELECT m.status AS grade, COUNT(*) cnt
             FROM marks m JOIN exam_subjects es ON es.id = m.exam_subject_id
             WHERE es.exam_id = ? GROUP BY m.status ORDER BY m.status", [$examId]);
        $subjectPerf = db_all(
            "SELECT su.code, su.name, ROUND(AVG(m.obtained),2) avg_marks, ROUND(AVG(m.obtained / m.full_marks * 100),1) avg_pct
             FROM marks m JOIN exam_subjects es ON es.id = m.exam_subject_id JOIN subjects su ON su.id = es.subject_id
             WHERE es.exam_id = ? GROUP BY es.subject_id ORDER BY su.code", [$examId]);
    } else {
        $summary['total'] = (int) db_val('SELECT COUNT(*) FROM results WHERE exam_id=?', [$examId]);
        $summary['pass'] = (int) db_val("SELECT COUNT(*) FROM results WHERE exam_id=? AND result_status='Pass'", [$examId]);
        $summary['fail'] = (int) db_val("SELECT COUNT(*) FROM results WHERE exam_id=? AND result_status='Fail'", [$examId]);
        $summary['absent'] = (int) db_val("SELECT COUNT(*) FROM results WHERE exam_id=? AND result_status='Absent'", [$examId]);
        $summary['pending'] = $summary['total'] - $summary['pass'] - $summary['fail'] - $summary['absent'];

        $avgPassPct = ($summary['pass'] + $summary['fail']) > 0 ? round(($summary['pass'] / ($summary['pass'] + $summary['fail'])) * 100, 1) : 0;
        $avgG = db_val("SELECT ROUND(AVG(total_marks),2) FROM result_details rd JOIN results r ON r.id=rd.result_id WHERE r.exam_id=?", [$examId]);
        $avgMarks = $avgG ?? 0;

        $gradeDist = db_all(
            "SELECT rd.grade, COUNT(*) cnt FROM results r JOIN result_details rd ON rd.result_id=r.id WHERE r.exam_id=? GROUP BY rd.grade ORDER BY rd.grade",
            [$examId]);
        $subjectPerf = db_all(
            "SELECT su.code, su.name, ROUND(AVG(rd.total_marks),2) avg_marks, ROUND(AVG(rd.percentage),1) avg_pct
             FROM results r JOIN result_details rd ON rd.result_id=r.id JOIN subjects su ON su.id=rd.subject_id
             WHERE r.exam_id=? GROUP BY rd.subject_id ORDER BY su.code", [$examId]);
    }
}
$totalPts = array_sum(array_column($gradeDist, 'cnt'));

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Analytics</h4>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-3"><select class="form-select" name="program" onchange="this.form.submit()">
        <option value="0">All programs</option>
        <?php foreach ($programs as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo selected($program, $p['id']); ?>><?php echo e($p['name']); ?></option><?php endforeach; ?>
    </select></div>
    <div class="col-md-4"><select class="form-select" name="exam_id" onchange="this.form.submit()">
        <?php foreach ($exams as $x): ?><option value="<?php echo $x['id']; ?>" <?php echo selected($examId, $x['id']); ?>><?php echo e($x['name'] . ' (' . $x['type_name'] . ')' . ($x['program_name'] ? ' — ' . $x['program_name'] : '')); ?></option><?php endforeach; ?>
    </select></div>
</form>

<div class="row g-3">
    <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-title">Total Students</div><div class="stat-value"><?php echo $summary['total']; ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#16a34a"><div class="card-body"><div class="stat-title">Passed</div><div class="stat-value text-success"><?php echo $summary['pass']; ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#dc2626"><div class="card-body"><div class="stat-title">Failed</div><div class="stat-value text-danger"><?php echo $summary['fail']; ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#d97706"><div class="card-body"><div class="stat-title">Pass %</div><div class="stat-value text-warning"><?php echo $avgPassPct; ?>%</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#0891b2"><div class="card-body"><div class="stat-title"><?php echo $isTerminal ? 'Avg %' : 'Avg Marks'; ?></div><div class="stat-value text-info"><?php echo $avgMarks; ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card" style="border-left-color:#7c3aed"><div class="card-body"><div class="stat-title">Absent</div><div class="stat-value text-purple"><?php echo $summary['absent']; ?></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body"><div class="stat-title">Pending / Other</div><div class="stat-value"><?php echo $summary['pending']; ?></div></div></div></div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><?php echo $isTerminal ? 'Status Distribution' : 'Grade Distribution'; ?></div>
            <div class="card-body">
                <?php if ($totalPts === 0): ?><p class="text-muted mb-0">No data yet.</p>
                <?php else: foreach ($gradeDist as $g): $pct = round(($g['cnt'] / $totalPts) * 100, 1); ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge text-bg-primary" style="width:70px"><?php echo e($g['grade']); ?></span>
                        <div class="progress flex-grow-1" style="height:14px">
                            <div class="progress-bar" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                        <span class="small text-muted" style="width:80px"><?php echo $g['cnt']; ?> (<?php echo $pct; ?>%)</span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Subject Performance (avg marks)</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Code</th><th>Subject</th><th style="min-width:220px">Avg Marks</th><th>Avg %</th></tr></thead>
                    <tbody>
                    <?php foreach ($subjectPerf as $s): $m = (float) $s['avg_marks'];
                        $full = $isTerminal ? (float) db_val('SELECT full_marks FROM exam_subjects WHERE exam_id=? AND subject_id=?', [$examId, (int) db_val('SELECT id FROM subjects WHERE code=?', [$s['code']])]) : 100;
                        $full = $full > 0 ? $full : 100; ?>
                        <tr>
                            <td><span class="badge text-bg-dark"><?php echo e($s['code']); ?></span></td>
                            <td><?php echo e($s['name']); ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:12px">
                                        <div class="progress-bar bg-info" style="width:<?php echo min(100, ($m / $full) * 100); ?>%"></div>
                                    </div>
                                    <span class="small"><?php echo $m; ?></span>
                                </div>
                            </td>
                            <td><?php echo e($s['avg_pct']); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$subjectPerf): ?><tr><td colspan="4" class="text-center py-4 text-muted">No marks/computed results yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>