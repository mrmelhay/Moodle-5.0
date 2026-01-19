<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/grouplib.php');

/**
 * Get quizzes that are active or close on the specific date.
 *
 * @param int $timestamp The timestamp of the date (00:00:00).
 * @return array List of quizzes.
 */
/**
 * Get quizzes that are active or occur on the specific date.
 *
 * @param int $timestamp The timestamp of the date (00:00:00).
 * @return array List of quizzes.
 */
function local_examreport_get_quizzes_by_date($timestamp) {
    global $DB;

    // Calculate start and end of the day.
    $daystart = $timestamp;
    $dayend = $timestamp + 86400 - 1;

    // Logic: Find quizzes where:
    // 1. Scheduled for today (Starts or Ends today)
    // 2. OR Have ACTIVITY today (Attempts started or finished today)
    // This ensures we catch "Always Open" quizzes if they are being used, 
    // and "Strict Schedule" quizzes even if no one took them yet.
    
    $sql = "SELECT DISTINCT q.id, q.course, q.name, c.fullname as coursename, c.shortname as courseshortname, cc.name as categoryname,
                   q.timeopen, q.timeclose, cm.id as cmid
            FROM {quiz} q
            JOIN {course} c ON q.course = c.id
            JOIN {course_categories} cc ON c.category = cc.id
            JOIN {course_modules} cm ON cm.instance = q.id
            JOIN {modules} m ON cm.module = m.id
            LEFT JOIN {quiz_attempts} qa ON qa.quiz = q.id
            WHERE m.name = 'quiz' AND (
               (q.timeopen >= :s1 AND q.timeopen <= :e1)
               OR (q.timeclose >= :s2 AND q.timeclose <= :e2)
               OR (qa.timestart >= :s3 AND qa.timestart <= :e3)
               OR (qa.timefinish >= :s4 AND qa.timefinish <= :e4)
            )";

    $params = [
        's1' => $daystart, 'e1' => $dayend,
        's2' => $daystart, 'e2' => $dayend,
        's3' => $daystart, 'e3' => $dayend,
        's4' => $daystart, 'e4' => $dayend
    ];

    return $DB->get_records_sql($sql, $params);
}

/**
 * Get results for a specific quiz, optionally filtered by date.
 *
 * @param int $quizid
 * @param string $dateparam Y-m-d string or empty
 * @param string $sort
 * @param string $dir
 * @return array List of student results.
 */
function local_examreport_get_quiz_results($quizid, $dateparam = '', $sort = '', $dir = 'ASC') {
    global $DB;

    // Validate sort column to prevent SQL injection.
    $allowed_sort = ['fullname', 'username', 'email', 'timestart', 'timefinish', 'duration', 'finalgrade'];
    if (!in_array($sort, $allowed_sort)) {
        $sort = 'finalgrade'; // Default per user request
        if ($dir === 'ASC') $dir = 'DESC'; // Default to DESC for Grade if not explicitly set
    } else {
        // Map sort keys
        $sortmap = [
            'fullname' => 'u.lastname, u.firstname',
            'username' => 'u.username',
            'email' => 'u.email',
            'timestart' => 'qa.timestart',
            'timefinish' => 'qa.timefinish',
            'duration' => '(qa.timefinish - qa.timestart)',
            'finalgrade' => 'qg.grade'
        ];
        if (isset($sortmap[$sort])) {
            $sort = $sortmap[$sort];
        }
    }
    
    // Ensure dir is valid
    $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

    // Date Filtering Logic
    $datecondition = "";
    $params = ['quizid' => $quizid];
    
    if ($dateparam) {
        $daystart = strtotime($dateparam);
        $dayend = $daystart + 86400 - 1;
        // Filter attempts that STARTED or FINISHED on this day.
        $datecondition = "AND (
            (qa.timestart >= :s1 AND qa.timestart <= :e1) OR 
            (qa.timefinish >= :s2 AND qa.timefinish <= :e2)
        )";
        $params['s1'] = $daystart;
        $params['e1'] = $dayend;
        $params['s2'] = $daystart;
        $params['e2'] = $dayend;
    }

    $sql = "SELECT
                qa.id as attemptid,
                u.id as userid,
                u.firstname,
                u.lastname,
                u.username,
                u.email,
                qg.grade as finalgrade,
                qa.state,
                qa.timestart,
                qa.timefinish
            FROM {quiz_attempts} qa
            JOIN {user} u ON qa.userid = u.id
            LEFT JOIN {quiz_grades} qg ON qa.quiz = qg.quiz AND qa.userid = qg.userid
            WHERE qa.quiz = :quizid AND QA.preview = 0
            $datecondition
            ORDER BY $sort $dir";

    $attempts = $DB->get_records_sql($sql, $params);
    
    $results = [];
    foreach ($attempts as $attempt) {
        // Format Email (remove @ part and ID/username if present)
        $emailparts = explode('@', $attempt->email);
        $email = $emailparts[0];
        // Remove username (ID) from email if strictly present
        $email = str_replace($attempt->username, '', $email);
        $email = trim($email, '-_ '); // Trim separators

        // Format Dates
        $timestart = $attempt->timestart ? date('d.m.Y H:i:s', $attempt->timestart) : '-';
        $timefinish = $attempt->timefinish ? date('d.m.Y H:i:s', $attempt->timefinish) : '-';

        // Calculate Duration
        $duration = '-';
        if ($attempt->timefinish > 0 && $attempt->timestart > 0) {
            $diff = $attempt->timefinish - $attempt->timestart;
            $hours = floor($diff / 3600);
            $minutes = floor(($diff / 60) % 60);
            $seconds = $diff % 60;
            $duration = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        $results[] = (object)[
            'attemptid' => $attempt->attemptid,
            'userid' => $attempt->userid,
            'fullname' => fullname((object)['firstname' => $attempt->firstname, 'lastname' => $attempt->lastname]),
            'username' => $attempt->username,
            'email' => $email,
            'status' => get_string('status_' . $attempt->state, 'local_examreport'),
            'grade' => $attempt->finalgrade === null ? '-' : format_float($attempt->finalgrade, 2),
            'timestart' => $timestart,
            'timefinish' => $timefinish,
            'duration' => $duration
        ];
    }

    return $results;
}

/**
 * Generate a secure token for public verification.
 *
 * @param int $quizid
 * @param string $dateparam
 * @return string
 */
function local_examreport_generate_token($quizid, $dateparam) {
    global $CFG;
    $secret = $CFG->siteidentifier . 'local_examreport_secret_salt'; 
    return md5($quizid . '|' . $dateparam . '|' . $secret);
}

/**
 * Verify the token.
 *
 * @param int $quizid
 * @param string $dateparam
 * @param string $token
 * @return bool
 */
function local_examreport_verify_token($quizid, $dateparam, $token) {
    $expected = local_examreport_generate_token($quizid, $dateparam);
    return $expected === $token;
}

/**
 * Check if a quiz has any in-progress attempts.
 *
 * @param int $quizid
 * @param string $dateparam Y-m-d string or empty
 * @return bool True if all attempts are finished (or no attempts), False if any inprogress.
 */
function local_examreport_is_quiz_fully_finished($quizid, $dateparam = '') {
    global $DB;
    
    $params = ['quizid' => $quizid, 'state' => 'inprogress'];
    $datecondition = "";

    if ($dateparam) {
        $daystart = strtotime($dateparam);
        $dayend = $daystart + 86400 - 1;
        // Check filtering logic matches report.php
        $datecondition = "AND (
            (timestart >= :s1 AND timestart <= :e1) OR 
            (timefinish >= :s2 AND timefinish <= :e2)
        )";
        $params['s1'] = $daystart; $params['e1'] = $dayend;
        $params['s2'] = $daystart; $params['e2'] = $dayend;
    }

    $sql = "SELECT COUNT(*) 
            FROM {quiz_attempts} 
            WHERE quiz = :quizid 
              AND state = :state
              AND preview = 0
              $datecondition";

    $inprogress_count = $DB->count_records_sql($sql, $params);

    return ($inprogress_count == 0);
}
