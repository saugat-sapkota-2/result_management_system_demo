<?php
require_once __DIR__ . '/../includes/init.php';
$user = require_role(ROLE_TEACHER);
$title = 'Submitted Marks';
$active = 'submitted';

$teacherId = (int) db_val('SELECT teacher_id FROM users WHERE id = ?', [$user['id']]);

$examId = (int) ($_GET['exam_id'] ?? 0);
$exams = db_all(
    "SELECT DISTINCT e.id, e.name, sm.name AS sem_name
     FROM exams e JOIN exam_subjects es ON es.exam_id = e.id AND es.status=1
     JOIN semesters sm ON sm.id = e.semester_id
     WHERE es.teacher_id = ? ORDER BY e.id DESC", [$teacherId]);
if (!$examId && $exams) $examId = (int) $exams[0]['id'];

$rows = $examId
    ? db_all(
        "SELECT es.id es_id, su.code, su.name AS subject_name, es.full_marks, es.marks_submitted, es.reopened,
                (SELECT COUNT(*) FROM marks m WHERE m.exam_subject_id = es.id) entered,
                (SELECT COUNT(*) FROM marks m WHERE m.exam_subject_id = es.id AND m.submitted = 1) submitted,
                (SELECT COUNT(*) FROM student_subjects ss WHERE ss.subject_id = es.subject_id AND ss.status = 1) expected
         FROM exam_subjects es JOIN subjects su ON su.id = es.subject_id
         WHERE es.exam_id = ? AND es.teacher_id = ? AND es.status = 1
         ORDER BY su.code", [$examId, $teacherId])
    : [];

include __DIR__ . '/../includes/header.php';
?>
<div class="page-title-row">
    <h4 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Submitted Marks</h4>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-6"><select class="form-select" name="exam_id" onchange="this.form.submit()">
        <?php foreach ($exams as $x): ?><option value="<?php echo $x['id']; ?>" <?php echo selected($examId, $x['id']); ?>><?php echo e($x['name'] . ' (' . $x['sem_name'] . ')'); ?></option><?php endforeach; ?>
    </select></div>
</form>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Subject</th><th>Full Marks</th><th style="min-width:200px">Progress (entered / submitted)</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row):
                $expected = max(1, (int) $row['expected']);
                $sPct = (int) round(((int) $row['submitted'] / $expected) * 100); ?>
                <tr>
                    <td><span class="badge text-bg-dark"><?php echo e($row['code']); ?></span> <?php echo e($row['subject_name']); ?></td>
                    <td><?php echo e($row['full_marks']); ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:12px"><div class="progress-bar bg-<?php echo $sPct >= 100 ? 'success' : 'info'; ?>" style="width:<?php echo $sPct; ?>%"></div></div>
                            <div class="small"><?php echo $row['entered']; ?> / <?php echo $row['submitted']; ?></div>
                        </div>
                    </td>
                    <td>
                        <?php if ((int) $row['reopened'] === 1): ?><span class="badge bg-warning text-dark">Correction Open</span>
                        <?php elseif ($row['marks_submitted']): ?><span class="badge bg-success">Submitted</span>
                        <?php elseif ((int) $row['entered'] > 0): ?><span class="badge bg-warning text-dark">Draft</span>
                        <?php else: ?><span class="badge bg-secondary">Not Started</span><?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm <?php echo ((int) $row['reopened'] === 1 || !$row['marks_submitted']) ? 'btn-outline-warning' : 'btn-outline-primary'; ?>" href="mark_entry.php?exam_id=<?php echo $examId; ?>&es_id=<?php echo $row['es_id']; ?>"><?php echo ((int) $row['reopened'] === 1 || !$row['marks_submitted']) ? '<i class="bi bi-pencil"></i> Edit' : '<i class="bi bi-eye"></i> View'; ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="5" class="text-center py-4 text-muted">No marks sheets assigned to you for this exam.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>