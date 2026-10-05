<?php
/**
 * RMS -- Result computation engine (simple model)
 * Final exams only: per-subject marks -> percentage -> grade -> grade point ->
 * credit point -> SGPA -> CGPA.
 *
 * Terminal exams never produce a `results` row: their published sheet is simply
 * the subject-wise marks (see terminal_student_summary()).
 */

/**
 * Compute + store the full result for one student + final exam.
 * Returns the result id (or null when not applicable).
 */
function compute_student_result(int $studentId, int $examId): ?int
{
    $exam = db_one('SELECT * FROM exams WHERE id = ?', [$examId]);
    if (!$exam) return null;
    if (exam_is_terminal($exam)) return null; // terminals have no SGPA/CGPA results

    // Remove existing non-locked result for a clean recompute
    $old = db_one('SELECT id, is_locked FROM results WHERE student_id = ? AND exam_id = ?', [$studentId, $examId]);
    if ($old && (int) $old['is_locked'] === 1) {
        return (int) $old['id']; // locked results are never silently changed
    }
    if ($old) {
        db_run('DELETE FROM result_details WHERE result_id = ?', [$old['id']]);
        db_run('DELETE FROM results WHERE id = ?', [$old['id']]);
    }

    $rules = grading_rules();
    $passPercent = $rules['pass_percent'];

    $examSubjects = db_all(
        "SELECT es.id es_id, es.subject_id, es.full_marks, su.credit_hours
         FROM exam_subjects es JOIN subjects su ON su.id = es.subject_id
         WHERE es.exam_id = ? AND es.status = 1 ORDER BY su.code", [$examId]);

    if (!$examSubjects) return null;

    $details = [];
    $anySubject = false;
    foreach ($examSubjects as $es) {
        $mark = db_one(
            'SELECT obtained, status FROM marks WHERE exam_subject_id = ? AND student_id = ?',
            [$es['es_id'], $studentId]);

        $full = (float) $es['full_marks'];
        if ($full <= 0) continue;
        $anySubject = true;

        $obtained = $mark ? (float) ($mark['obtained'] ?? 0) : 0;
        $st = $mark['status'] ?? 'Present';
        $hasAbsent = false; $hasWithheld = false; $hasIncomplete = false; $hasNonEligible = false;
        if ($st === 'Absent') { $hasAbsent = true; $obtained = 0; }
        elseif ($st === 'Withheld') { $hasWithheld = true; $obtained = 0; }
        elseif ($st === 'Incomplete') { $hasIncomplete = true; $obtained = 0; }
        elseif ($st === 'Not Eligible') { $hasNonEligible = true; $obtained = 0; }

        $pct = ($obtained / $full) * 100;
        $passed = $pct >= $passPercent && (!$hasAbsent && !$hasWithheld && !$hasIncomplete && !$hasNonEligible);

        if ($hasAbsent) $subjStatus = 'Absent';
        elseif ($hasWithheld) $subjStatus = 'Withheld';
        elseif ($hasIncomplete) $subjStatus = 'Incomplete';
        elseif ($hasNonEligible) $subjStatus = 'Not Eligible';
        elseif (!$passed) $subjStatus = 'Fail';
        else $subjStatus = 'Pass';

        $g = get_grade($pct);
        $credits = (float) $es['credit_hours'];
        $gp = $passed ? (float) $g['grade_point'] : 0.0;
        $cp = $credits * $gp;

        $details[] = [
            'subject_id' => $es['subject_id'],
            'internal_max' => 0,
            'internal_total' => 0,
            'external_max' => $full,
            'external_total' => $obtained,
            'max_marks' => $full,
            'total_marks' => $obtained,
            'percentage' => $pct,
            'grade' => $passed ? $g['grade'] : 'F',
            'grade_point' => $gp,
            'credit_hours' => $credits,
            'credit_point' => $cp,
            'subject_status' => $subjStatus,
        ];
    }

    if (!$anySubject) return null;

    $totalCredits = 0.0; $totalCreditPoints = 0.0;
    $hasAbsent = false; $hasWithheld = false; $hasIncomplete = false; $hasFail = false; $hasNonEligible = false;
    foreach ($details as $d) {
        $totalCredits += (float) $d['credit_hours'];
        $totalCreditPoints += (float) $d['credit_point'];
        if ($d['subject_status'] === 'Absent') $hasAbsent = true;
        if ($d['subject_status'] === 'Withheld') $hasWithheld = true;
        if ($d['subject_status'] === 'Incomplete') $hasIncomplete = true;
        if ($d['subject_status'] === 'Not Eligible') $hasNonEligible = true;
        if ($d['subject_status'] === 'Fail') $hasFail = true;
    }
    $sgpa = $totalCredits > 0 ? $totalCreditPoints / $totalCredits : 0;

    if ($hasAbsent) $resultStatus = 'Absent';
    elseif ($hasWithheld) $resultStatus = 'Withheld';
    elseif ($hasIncomplete) $resultStatus = 'Incomplete';
    elseif ($hasNonEligible) $resultStatus = 'Not Eligible';
    elseif ($hasFail) $resultStatus = 'Fail';
    else $resultStatus = 'Pass';

    db_run('INSERT INTO results (student_id, exam_id, semester_id, total_credits, total_credit_points, sgpa, cgpa, result_status, calculated_by, calculated_at)
            VALUES (?,?,?,?,?,?,0,?,?,NOW())',
        [$studentId, $examId, $exam['semester_id'], $totalCredits, $totalCreditPoints, $sgpa, $resultStatus, user_id_or_null()]);
    $resultId = last_id();

    foreach ($details as $d) {
        db_run('INSERT INTO result_details (result_id, student_id, subject_id, internal_max, internal_total, external_max, external_total,
                max_marks, total_marks, percentage, grade, grade_point, credit_hours, credit_point, subject_status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$resultId, $studentId, $d['subject_id'], $d['internal_max'], $d['internal_total'], $d['external_max'], $d['external_total'],
             $d['max_marks'], $d['total_marks'], $d['percentage'], $d['grade'], $d['grade_point'], $d['credit_hours'], $d['credit_point'], $d['subject_status']]);
    }

    // CGPA refresh across all of the student's published finals
    $all = db_all('SELECT total_credits, total_credit_points FROM results WHERE student_id = ? AND is_locked = 1', [$studentId]);
    $tc = (float) $totalCredits; $tcp = (float) $totalCreditPoints; // current exam
    foreach ($all as $r) { $tc += (float) $r['total_credits']; $tcp += (float) $r['total_credit_points']; }
    $cgpa = $tc > 0 ? $tcp / $tc : 0;
    db_run('UPDATE results SET cgpa = ? WHERE id = ?', [$cgpa, $resultId]);

    return $resultId;
}

/** Enrolled students of an exam (from subject enrolment of its semester subjects). */
function exam_students(int $examId): array
{
    return db_all(
        "SELECT DISTINCT st.id, st.student_code, st.full_name, st.exam_roll_no, b.name AS batch_name, s.name AS section_name
         FROM exam_subjects es
         JOIN student_subjects ss ON ss.subject_id = es.subject_id AND ss.status = 1
         JOIN students st ON st.id = ss.student_id AND st.status = 1
         JOIN exams e ON e.id = es.exam_id
         LEFT JOIN batches b ON b.id = st.batch_id
         LEFT JOIN sections s ON s.id = st.section_id
         WHERE es.exam_id = ? AND es.status = 1 AND st.program_id = e.program_id
         ORDER BY st.student_code", [$examId]);
}