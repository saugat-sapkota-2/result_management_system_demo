<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/result_engine.php';
require_once __DIR__ . '/../includes/academics.php';
$user = require_role(ROLE_SUPERADMIN);
require_module('reports');
$title = 'Reports';
$active = 'reports';

$programs = db_all('SELECT * FROM programs WHERE status=1 ORDER BY name');
$program = (int) ($_GET['program'] ?? 0);
$exams = db_all('SELECT e.*, s.name AS sem_name, et.name AS type_name, p.name AS program_name FROM exams e
    JOIN semesters s ON s.id = e.semester_id JOIN exam_types et ON et.id = e.exam_type_id
    LEFT JOIN programs p ON p.id = e.program_id
    WHERE (? = 0 OR e.program_id = ?) ORDER BY e.id DESC', [$program, $program]);
$examId = (int) ($_GET['exam_id'] ?? ($exams[0]['id'] ?? 0));
$exam = $examId ? db_one('SELECT * FROM exams WHERE id=?', [$examId]) : null;
$filter = $_GET['filter'] ?? 'all';
$isTerminal = $exam ? exam_is_terminal($exam) : false;

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-flag me-2"></i>Reports</h4>
    <div class="d-flex gap-2 no-print">
        <a class="btn btn-sm btn-outline-secondary" href="analytics.php"><i class="bi bi-bar-chart"></i> Analytics</a>
        <button class="btn btn-sm btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
</div>

<form method="get" class="row g-2 mb-3 no-print">
    <div class="col-md-2">
        <select class="form-select" name="program" onchange="this.form.submit()">
            <option value="0">All programs</option>
            <?php foreach ($programs as $p): ?><option value="<?php echo $p['id']; ?>" <?php echo selected($program, $p['id']); ?>><?php echo e($p['name']); ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <select class="form-select" name="exam_id" onchange="this.form.submit()">
            <?php foreach ($exams as $x): ?><option value="<?php echo $x['id']; ?>" <?php echo selected($examId, $x['id']); ?>><?php echo e($x['name'] . ' (' . $x['type_name'] . ')' . ($x['program_name'] ? ' — ' . $x['program_name'] : '')); ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select class="form-select" name="filter" onchange="this.form.submit()">
            <option value="all" <?php echo selected($filter, 'all'); ?>>All Students</option>
            <option value="Pass" <?php echo selected($filter, 'Pass'); ?>>Passed Students</option>
            <?php if (!$isTerminal): ?>
                <option value="Fail" <?php echo selected($filter, 'Fail'); ?>>Failed Students</option>
                <option value="Absent" <?php echo selected($filter, 'Absent'); ?>>Absent Students</option>
            <?php endif; ?>
            <option value="retained" <?php echo selected($filter, 'retained'); ?>>Retained / Other</option>
        </select>
    </div>
</form>

<?php if (!$exam): ?>
    <div class="alert alert-info">Create an examination first to generate reports.</div>
<?php else:
    $students = exam_students($examId);
    $resultRows = [];
    if ($isTerminal) {
        foreach ($students as $st) {
            $sum = terminal_student_summary($examId, (int) $st['id']);
            if (!$sum || !$sum['subjects']) continue;
            $s = $sum['overall'];
            if ($filter === 'all' || $s === $filter || ($filter === 'retained' && $s !== 'Pass')) {
                $resultRows[] = array_merge($st, ['result' => ['status' => $s, 'sum' => $sum]]);
            }
        }
        $passCount = count(array_filter($resultRows, fn($x) => $x['result']['status'] === 'Pass'));
    } else {
        foreach ($students as $st) {
            $r = db_one('SELECT * FROM results WHERE student_id = ? AND exam_id = ?', [$st['id'], $examId]);
            if (!$r) continue;
            if ($filter === 'all' || ($filter !== 'retained' && $r['result_status'] === $filter) || ($filter === 'retained' && $r['result_status'] !== 'Pass')) {
                $resultRows[] = array_merge($st, ['result' => $r]);
            }
        }
        $passCount = count(array_filter($resultRows, fn($x) => $x['result']['result_status'] === 'Pass'));
    }
?>
<div class="card mb-3 no-print">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <span><strong>Students:</strong> <?php echo count($students); ?></span>
        <span class="text-success"><strong>Passed:</strong> <?php echo $passCount; ?></span>
        <span><strong>Included:</strong> <?php echo count($resultRows); ?></span>
        <span class="ms-auto d-flex gap-2">
            <?php if ($exam['exam_status'] === 'published'): ?>
                <a class="btn btn-sm btn-outline-danger" href="<?php echo e(url('pdf/marksheet_group.php?exam_id=' . $examId . '&filter=' . $filter)); ?>"><i class="bi bi-file-earmark-pdf"></i> PDF Group Marksheet</a>
            <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary" disabled title="Publish the exam to generate PDFs"><i class="bi bi-file-earmark-pdf"></i> PDF (publish to enable)</button>
            <?php endif; ?>
        </span>
    </div>
</div>

<ul class="nav nav-tabs no-print" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabStudents"><?php echo $isTerminal ? 'Terminal Marks' : 'Semester Result'; ?></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSubjects">Subject-wise Marks</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabGrades">Grade / Status Distribution</button></li>
</ul>
<div class="tab-content border border-top-0 rounded-bottom bg-white">

    <div class="tab-pane fade show active" id="tabStudents">
        <div class="print-header d-none"><h4 class="text-center mb-0"><?php echo e(get_setting('institute_name')); ?></h4>
            <p class="text-center text-muted"><?php echo e($exam['name']); ?> — <?php echo $isTerminal ? 'Terminal Marks Report' : 'Semester Result Report'; ?></p></div>
        <?php if ($isTerminal): ?>
        <table class="table table-bordered table-sm mb-0">
            <thead><tr><th>Roll No.</th><th>Name</th><th class="text-center">Full</th><th class="text-center">Obtained</th><th class="text-center">%</th><th>Result</th><th class="no-print text-end">Sheet</th></tr></thead>
            <tbody>
            <?php foreach ($resultRows as $row): $r = $row['result']; ?>
                <tr>
                    <td><?php echo e($row['student_code']); ?></td>
                    <td><?php echo e($row['full_name']); ?></td>
                    <td class="text-center"><?php echo marks_round($r['sum']['full']); ?></td>
                    <td class="text-center"><?php echo marks_round($r['sum']['total']); ?></td>
                    <td class="text-center"><?php echo round($r['sum']['percentage'], 1); ?>%</td>
                    <td><?php echo status_badge($r['status']); ?></td>
                    <td class="no-print text-end"><a class="btn btn-sm btn-outline-danger" href="<?php echo e(url('pdf/marksheet.php?exam_id=' . $examId . '&student_id=' . $row['id'])); ?>"><i class="bi bi-file-pdf"></i></a>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo e(url('reports/individual_result.php?exam_id=' . $examId . '&student_id=' . $row['id'])); ?>"><i class="bi bi-eye"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$resultRows): ?><tr><td colspan="7" class="text-center py-4 text-muted">No marks match this filter.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <?php else: ?>
        <table class="table table-bordered table-sm mb-0">
            <thead><tr><th>Roll No.</th><th>Name</th><th>Credits</th><th>Credit Points</th><th>SGPA</th><th>CGPA</th><th>Result</th><th class="no-print text-end">Marksheet</th></tr></thead>
            <tbody>
            <?php foreach ($resultRows as $row): $r = $row['result']; ?>
                <tr>
                    <td><?php echo e($row['student_code']); ?></td>
                    <td><?php echo e($row['full_name']); ?></td>
                    <td><?php echo e($r['total_credits']); ?></td>
                    <td><?php echo e($r['total_credit_points']); ?></td>
                    <td><?php echo fmt_gpa($r['sgpa']); ?></td>
                    <td><?php echo fmt_gpa($r['cgpa']); ?></td>
                    <td><?php echo status_badge($r['result_status']); ?></td>
                    <td class="no-print text-end">
                        <a class="btn btn-sm btn-outline-danger" href="<?php echo e(url('pdf/marksheet.php?exam_id=' . $examId . '&student_id=' . $row['id'])); ?>"><i class="bi bi-file-pdf"></i></a>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo e(url('reports/individual_result.php?exam_id=' . $examId . '&student_id=' . $row['id'])); ?>"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$resultRows): ?><tr><td colspan="8" class="text-center py-4 text-muted">No results match this filter.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="tabSubjects">
        <?php
        $examSubjects = db_all("SELECT es.id, es.subject_id, es.full_marks, su.code, su.name subject_name
                                 FROM exam_subjects es JOIN subjects su ON su.id = es.subject_id
                                 WHERE es.exam_id = ? AND es.status = 1 ORDER BY su.code", [$examId]);
        foreach ($examSubjects as $es):
            $marks = db_all(
                "SELECT st.student_code, st.full_name, m.obtained, m.full_marks fm, m.status, m.submitted
                 FROM marks m JOIN students st ON st.id = m.student_id
                 WHERE m.exam_subject_id = ? ORDER BY st.student_code",
                [$es['id']]);
            ?>
            <h6 class="mt-3 px-3 pt-3"><?php echo e($es['code'] . ' — ' . $es['subject_name']); ?>
                <span class="text-muted small ms-2">Full Marks: <?php echo marks_round($es['full_marks']); ?></span></h6>
            <table class="table table-sm table-striped mb-3">
                <thead><tr><th>Roll No.</th><th>Student</th><th class="text-center">Obtained</th><th class="text-center">%</th><th class="text-center">Status</th><th class="text-center">Submitted?</th></tr></thead>
                <tbody>
                <?php foreach ($marks as $m): ?>
                    <tr><td><?php echo e($m['student_code']); ?></td><td><?php echo e($m['full_name']); ?></td>
                        <td class="text-center"><?php echo marks_round($m['obtained']); ?></td>
                        <td class="text-center"><?php echo (float) $m['fm'] > 0 ? round(((float) $m['obtained'] / (float) $m['fm']) * 100, 1) : 0; ?>%</td>
                        <td class="text-center"><?php echo status_badge($m['status']); ?></td>
                        <td class="text-center"><?php echo $m['submitted'] ? 'Yes' : 'No'; ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$marks): ?><tr><td colspan="6" class="text-center text-muted py-2">No marks yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    </div>

    <div class="tab-pane fade" id="tabGrades">
        <?php if ($isTerminal):
            $dist = db_all(
                "SELECT m.status, COUNT(*) cnt, ROUND(AVG(m.obtained / m.full_marks * 100), 1) avg_pct
                 FROM marks m JOIN exam_subjects es ON es.id = m.exam_subject_id
                 WHERE es.exam_id = ?
                 GROUP BY m.status ORDER BY m.status", [$examId]);
            $distTotal = array_sum(array_column($dist, 'cnt'));
            ?>
            <table class="table table-sm mb-0">
                <thead><tr><th>Status</th><th>Count</th><th>% of total</th><th>Avg. Percentage</th></tr></thead>
                <tbody>
                <?php foreach ($dist as $d): ?>
                    <tr><td><?php echo status_badge($d['status']); ?></td>
                        <td><?php echo $d['cnt']; ?></td>
                        <td><?php echo $distTotal ? round(($d['cnt'] / $distTotal) * 100, 1) : 0; ?>%</td>
                        <td><?php echo e($d['avg_pct']); ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$dist): ?><tr><td colspan="4" class="text-center py-4 text-muted">No marks recorded yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        <?php else:
            $dist = db_all(
                "SELECT rd.grade, COUNT(*) cnt, ROUND(AVG(rd.percentage),2) avg_pct
                 FROM results r JOIN result_details rd ON rd.result_id = r.id
                 WHERE r.exam_id = ? AND rd.subject_status = 'Pass'
                 GROUP BY rd.grade ORDER BY rd.grade", [$examId]);
            $distTotal = array_sum(array_column($dist, 'cnt'));
            ?>
            <table class="table table-sm mb-0">
                <thead><tr><th>Grade</th><th>Count</th><th>% of total</th><th>Avg. Percentage</th></tr></thead>
                <tbody>
                <?php foreach ($dist as $d): ?>
                    <tr><td><span class="badge text-bg-primary"><?php echo e($d['grade']); ?></span></td>
                        <td><?php echo $d['cnt']; ?></td>
                        <td><?php echo $distTotal ? round(($d['cnt'] / $distTotal) * 100, 1) : 0; ?>%</td>
                        <td><?php echo e($d['avg_pct']); ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$dist): ?><tr><td colspan="4" class="text-center py-4 text-muted">No grades computed yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>