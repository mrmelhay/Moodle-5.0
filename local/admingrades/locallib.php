<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Add a record to admin_grades.
 */
function local_admingrades_add_record($userid, $ball, $date) {
    global $DB;
    $record = new stdClass();
    $record->user_id = $userid;
    $record->ball = $ball;
    $record->date = $date;
    $record->attempt = ''; // Initially empty
    
    // User asked for 'nextval' logic for ID, but Moodle handles AutoInc.
    // User supplied table def has 'id' PK.
    $DB->insert_record('admin_grades', $record);
}

/**
 * Process grades: Find latest attempt and update score.
 */
function local_admingrades_process_grades() {
    global $DB;
    
    // Get all records where attempt is empty or we want to re-process?
    // User said "tizim ... topib ... yozish kerak". Let's process ALL or maybe those with null attempt?
    // Let's assume we process everything to enforce the grade.
    
    $records = $DB->get_records('admin_grades');
    $count = 0;

    foreach ($records as $r) {
        $daystart = strtotime($r->date);
        $dayend = $daystart + 86400 - 1;

        // Find LAST attempt for this user on this date
        // Join quiz to ensure it's a quiz attempt
        $sql = "SELECT qa.*, q.id as quizid, q.course, q.name
                FROM {quiz_attempts} qa
                JOIN {quiz} q ON qa.quiz = q.id
                WHERE qa.userid = :userid
                  AND (
                      (qa.timefinish >= :s1 AND qa.timefinish <= :e1)
                      OR
                      (qa.timestart >= :s2 AND qa.timestart <= :e2)
                  )
                  AND qa.state = 'finished'
                  AND qa.preview = 0
                ORDER BY qa.timefinish DESC";
        
        $params = [
            'userid' => $r->user_id,
            's1' => $daystart, 'e1' => $dayend,
            's2' => $daystart, 'e2' => $dayend
        ];

        // Get the single latest attempt
        $attempts = $DB->get_records_sql($sql, $params, 0, 1);
        
        if ($attempts) {
            $attempt = reset($attempts);
            
            // Check if grade matches desired ball
            // Moodle grades are stored in quiz_grades (final) and quiz_attempt (sumgrades).
            // But 'ball' implies the final score.
            
            // Updating DB directly to force the grade (since this is an override)
            // 1. Update quiz_attempts sumgrades (if applicable, but usually calculated)
            // But cleaner way: We want to set the FINAL GRADE for the quiz.
            
            // However, Moodle recalculates grades.
            // If we just want to override the final grade:
            
            // A. Update {quiz_grades}
            $grade = $DB->get_record('quiz_grades', ['quiz' => $attempt->quizid, 'userid' => $r->user_id]);
            if (!$grade) {
                $grade = new stdClass();
                $grade->quiz = $attempt->quizid;
                $grade->userid = $r->user_id;
                $grade->grade = $r->ball;
                $grade->timemodified = time();
                $DB->insert_record('quiz_grades', $grade);
            } else {
                $grade->grade = $r->ball;
                $grade->timemodified = time();
                $DB->update_record('quiz_grades', $grade);
            }

            // B. Update {quiz_attempts} sumgrades just in case
            $attempt->sumgrades = $r->ball;
            $DB->update_record('quiz_attempts', $attempt); // This might be risky if questions don't match sum, but requested.

            // C. Push to Gradebook
            // We need to use quiz_update_grades($quiz, $userid) but that might recalculate from questions.
            // To FORCE a grade in gradebook:
            $quiz = $DB->get_record('quiz', ['id' => $attempt->quizid]);
            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course);
            
            // Grade item update
            $grade_item = grade_item::fetch([
                'itemtype' => 'mod',
                'itemmodule' => 'quiz',
                'iteminstance' => $quiz->id,
                'courseid' => $quiz->course
            ]);
            
            if ($grade_item) {
                // Determine grade value
                $finalgrade = (float)$r->ball;
                $grade_item->update_final_grade($r->user_id, $finalgrade, 'local_admingrades');
            }

            // Update admin_grades table with attempt ID info
            $r->attempt = $attempt->id;
            $DB->update_record('admin_grades', $r);
            
            $count++;
        } else {
            // Attempt not found
            if ($r->attempt !== 'Not Found') {
                $r->attempt = 'Not Found';
                $DB->update_record('admin_grades', $r);
            }
        }
    }
    
    return $count;
}
