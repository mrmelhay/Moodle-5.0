<?php
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/locallib.php');

// Parameters.
$quizid = required_param('quizid', PARAM_INT);
$dateparam = optional_param('date', '', PARAM_TEXT);
$token = required_param('token', PARAM_ALPHANUM);
$sort = optional_param('sort', 'finalgrade', PARAM_TEXT);
$dir = optional_param('dir', 'DESC', PARAM_ALPHA);

// Verify Token.
if (!local_examreport_verify_token($quizid, $dateparam, $token)) {
    print_error('invalidtoken', 'local_examreport');
}

$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);

// Context (System context just for page setup, though we won't require login).
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/examreport/verify.php', ['quizid' => $quizid, 'date' => $dateparam, 'token' => $token]));
$PAGE->set_title(get_string('pluginname', 'local_examreport') . ': ' . $quiz->name);
$PAGE->set_heading($quiz->name);
$PAGE->set_pagelayout('popup');

// Process Data.
$results = local_examreport_get_quiz_results($quizid, $dateparam, $sort, $dir);

// Output.
echo $OUTPUT->header();

echo '<h3>' . get_string('course', 'local_examreport') . ': ' . s($course->fullname) . '</h3>';
echo '<h4>' . get_string('quiz', 'local_examreport') . ': ' . s($quiz->name) . '</h4>';

if ($dateparam) {
    echo '<h5>' . get_string('date') . ': ' . s($dateparam) . '</h5>';
}

$table = new html_table();

// Helper to create sortable link (preserving token)
function local_examreport_verify_sort_link($colname, $currentsort, $currentdir, $quizid, $dateparam, $token) {
    if ($currentsort == $colname) {
        $nextdir = ($currentdir == 'ASC') ? 'DESC' : 'ASC';
        $icon = ($currentdir == 'ASC') ? ' ↓' : ' ↑';
    } else {
        $nextdir = 'ASC';
        $icon = '';
    }
    $url = new moodle_url('/local/examreport/verify.php', ['quizid' => $quizid, 'date' => $dateparam, 'token' => $token, 'sort' => $colname, 'dir' => $nextdir]);
    return '<a href="' . $url . '">' . get_string($colname, 'local_examreport') . $icon . '</a>';
}

$table->head = [
    local_examreport_verify_sort_link('username', $sort, $dir, $quizid, $dateparam, $token),
    local_examreport_verify_sort_link('studentname', $sort, $dir, $quizid, $dateparam, $token),
    local_examreport_verify_sort_link('email', $sort, $dir, $quizid, $dateparam, $token),
    get_string('status', 'local_examreport'),
    local_examreport_verify_sort_link('timestart', $sort, $dir, $quizid, $dateparam, $token),
    local_examreport_verify_sort_link('timefinish', $sort, $dir, $quizid, $dateparam, $token),
    local_examreport_verify_sort_link('duration', $sort, $dir, $quizid, $dateparam, $token),
    local_examreport_verify_sort_link('grade', $sort, $dir, $quizid, $dateparam, $token),
];

$table->data = [];

foreach ($results as $row) {
    $table->data[] = [
        $row->username,
        $row->fullname,
        $row->email,
        $row->status,
        $row->timestart,
        $row->timefinish,
        $row->duration,
        $row->grade
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
