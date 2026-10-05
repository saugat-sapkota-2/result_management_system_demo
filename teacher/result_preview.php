<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/result_engine.php';
$user = require_role(ROLE_TEACHER);
$title = 'Result Preview';
$active = 'preview';

$teacherId = (int) db_val('SELECT teacher_id FROM users WHERE id = ?', [$user['id']]);

$examId = (int) ($_GET['exam_id'] ?? 0);
$exams = db_all(
    "SELECT DISTINCT e.id, e.name, sm.name AS sem_name
     FROM exams e JOIN exam_subjects es ON es.exam_id = e.id AND es.status=1
     JOIN semesters sm ON sm.id = e.semester_id
     WHERE es.teacher_id = ? ORDER BY e.id DESC", [$teacherId]);
if (!$examId && $exams) $examId = (int) $exams[0]['id'];

$esId = (int) ($_GET['es_id'] ?? 0);
$mySubjects = $examId ? db_all(
    "SELECT es.id es_id, es.full_marks, es.marks_submitted, su.code, su.name AS subject_name
     FROM exam_subjects es JOIN subjects su ON su.id = es.subject_id
     WHERE es.exam_id = ? AND es.teacher_id = ? AND es.status=1 ORDER BY su.code", [$examId, $teacherId]) : [];
if (!$esId && $mySubjects) $esId = (int) $mySubjects[0]['es_id'];

$rows = [];
if ($examId && $esId) {
    $rows = db_all(
        "SELECT st.id, st.student_code, st.full_name,
                m.obtained, m.status AS marks_status, m.full_marks, m.submitted
         FROM exam_subjects es
         JOIN student_subjects ss ON ss.subject_id = es.subject_id AND ss.status = 1
         JOIN students st ON st.id = ss.student_id AND st.status = 1
         JOIN exams e ON e.id = es.exam_id
         LEFT JOIN marks m ON m.exam_subject_id = es.id AND m.student_id = st.id
         WHERE es.id = ? AND st.program_id = e.program_id
         ORDER BY st.student_code", [$esId]);
}
$exam = null;
if ($examId) {
    $exam = db_one('SELECT * FROM exams WHERE id = ?', [$examId]);
}
$isTerminal = $exam ? exam_is_terminal($exam) : true;

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-eye me-2"></i>Result Preview (read-only)</h4>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-4"><select class="form-select" name="exam_id" onchange="this.form.submit()">
        <?php foreach ($exams as $x): ?><option value="<?php echo $x['id']; ?>" <?php echo selected($examId, $x['id']); ?>><?php echo e($x['name']); ?></option><?php endforeach; ?>
    </select></div>
    <div class="col-md-4"><select class="form-select" name="es_id" onchange="this.form.submit()">
        <?php foreach ($mySubjects as $s): ?><option value="<?php echo $s['es_id']; ?>" <?php echo selected($esId, $s['es_id']); ?>><?php echo e($s['code'] . ' — ' . $s['subject_name']); ?></option><?php endforeach; ?>
    </select></div>
</form>

<div class="card">
    <div class="card-header">Subject-wise Marks — <?php echo e($esId ? db_val('SELECT name FROM subjects WHERE id=?', [db_val('SELECT subject_id FROM exam_subjects WHERE id=?', [$esId])]) : ''); ?></div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Roll No.</th><th>Student</th><th class="text-center">Full Marks</th><th class="text-center">Obtained</th><th class="text-center">%</th><th class="text-center">Grade</th><th>Status</th><th class="text-center">Sheet</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                if ($r['obtained'] === null):
                    $full = (float) $r['full_marks'];
                    $obt = '—'; $pct = '—'; $grade = '—'; $st = 'Not entered'; $cls = 'bg-secondary'; ?>
                <?php else:
                    $tRow = terminal_subject_row(['subject_code'=>'','subject_name'=>'','credit_hours'=>0,'full_marks'=>$r['full_marks']], $r);
                    $obt = marks_round($r['obtained']); $pct = round($tRow['percentage'],1); $grade = $tRow['grade']; $st = $tRow['status']; $cls = $st === 'Pass' ? 'bg-success' : ($st === 'Fail' ? 'bg-danger' : 'bg-warning text-dark'); ?>
                <?php endif; ?>
                <tr>
                    <td><span class="badge text-bg-dark"><?php echo e($r['student_code']); ?></span></td>
                    <td class="fw-semibold"><?php echo e($r['full_name']); ?></td>
                    <td class="text-center"><?php echo marks_round($r['full_marks']); ?></td>
                    <td class="text-center fw-semibold"><?php echo $obt; ?></td>
                    <td class="text-center"><?php echo $pct; ?></td>
                    <td class="text-center"><?php echo $grade === '—' ? '—' : '<span class="badge text-bg-primary">' . e($grade) . '</span>'; ?></td>
                    <td><?php echo $st === '—' ? '<span class="badge bg-secondary">Not entered</span>' : '<span class="badge ' . $cls . '">' . e($st) . '</span>'; ?></td>
                    <td class="text-center">
                        <?php if ($r['submitted']): ?><span class="badge bg-info text-dark">Submitted</span>
                        <?php elseif ($r['obtained'] !== null): ?><span class="badge bg-warning text-dark">Draft</span>
                        <?php else: ?><span class="badge bg-secondary">—</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="8" class="text-center py-4 text-muted">No data.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>