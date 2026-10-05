<?php
/**
 * RMS -- Academic computation helpers (SGPA, CGPA, back subjects)
 */

/** Semester summaries for a student from computed results (optional status filter). */
function student_semesters_summary(int $studentId, string $statusFilter = ''): array
{
    $sql = "SELECT r.*, sm.name AS semester_name, sm.semester_no
            FROM results r JOIN semesters sm ON sm.id = r.semester_id
            WHERE r.student_id = ?";
    $params = [$studentId];
    if ($statusFilter !== '') {
        $sql .= " AND r.status IN (" . implode(',', array_fill(0, count(explode(',', $statusFilter)), '?')) . ")";
        foreach (explode(',', $statusFilter) as $s) $params[] = trim($s);
    }
    $sql .= " ORDER BY sm.semester_no";
    return db_all($sql, $params);
}

/** Cumulative CGPA over a list of semester results (credit-weighted). */
function cumulative_gpa(array $semesters): array
{
    $tc = 0.0; $tcp = 0.0; $sgpaSum = 0.0; $n = 0;
    $sgpaCount = 0; $sgpaW = 0.0;
    foreach ($semesters as $s) {
        $tc += (float) $s['total_credits'];
        $tcp += (float) $s['total_credit_points'];
        if ($s['result_status'] === 'Pass') {
            $sgpaSum += (float) $s['sgpa'] * (float) $s['total_credits'];
            $sgpaW += (float) $s['total_credits'];
            $sgpaCount++;
        }
    }
    $cgpa = $tc > 0 ? $tcp / $tc : 0;
    $weightedSgpa = $sgpaW > 0 ? $sgpaSum / $sgpaW : 0;
    return [
        'total_credits' => $tc,
        'total_credit_points' => $tcp,
        'cgpa' => $cgpa,
        'weighted_sgpa' => $weightedSgpa,
        'completed' => $sgpaCount,
    ];
}

/** Back / failed subjects for a student from their (locked) results. */
function student_back_subjects(int $studentId): array
{
    return db_all(
        "SELECT DISTINCT su.*, sm.name AS semester_name
         FROM result_details rd
         JOIN subjects su ON su.id = rd.subject_id
         JOIN results r ON r.id = rd.result_id
         JOIN semesters sm ON sm.id = r.semester_id
         WHERE rd.student_id = ? AND rd.subject_status = 'Fail' AND r.is_locked = 1
         ORDER BY sm.semester_no, su.code",
        [$studentId]
    );
}

/** Per-subject detail rows for one result (joined). */
function result_subject_rows(int $resultId): array
{
    return db_all(
        "SELECT rd.*, su.code AS subject_code, su.name AS subject_name, su.credit_hours
         FROM result_details rd JOIN subjects su ON su.id = rd.subject_id
         WHERE rd.result_id = ? ORDER BY su.code",
        [$resultId]
    );
}