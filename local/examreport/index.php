<?php
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/locallib.php');

// Parameters.
$dateparam = optional_param('date', '', PARAM_TEXT); // Y-m-d
$quizid = optional_param('quizid', 0, PARAM_INT);

// Context and Page setup.
$context = context_system::instance();
require_login();
// require_capability('moodle/site:config', $context); // Un-comment to restrict to admins if needed.

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/examreport/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_examreport'));
$PAGE->set_heading(get_string('reporttitle', 'local_examreport'));
$PAGE->set_pagelayout('report');

// Start Output.
echo $OUTPUT->header();

// 1. Date Selection Form.
// Using a simple HTML5 date input for simplicity and modern browser support.
$selected_date_timestamp = 0;
if ($dateparam) {
    $selected_date_timestamp = strtotime($dateparam);
} else {
    $dateparam = date('Y-m-d'); // Default today
    $selected_date_timestamp = time();
}

// Convert timestamp to start of day for accurate DB queries
$daystart = strtotime(date('Y-m-d', $selected_date_timestamp));

echo $OUTPUT->box_start();
echo '<form method="get" action="index.php" class="form-inline">';
echo '<div class="form-group">';
echo '<label for="date" class="mr-2"><strong>' . get_string('selectdate', 'local_examreport') . ': </strong></label>';
echo '<input type="date" name="date" id="date" value="' . s($dateparam) . '" class="form-control mr-2">';
echo '<button type="submit" class="btn btn-primary">' . get_string('showexams', 'local_examreport') . '</button>';
echo '</div>';
echo '</form>';
echo $OUTPUT->box_end();

// 2. Display Quizzes if Date Selected (and no specific quiz selected OR to show sidebar/list).
if ($daystart) {
    $quizzes = local_examreport_get_quizzes_by_date($daystart);

    if (empty($quizzes)) {
        echo $OUTPUT->notification(get_string('noexams', 'local_examreport'), 'info');
    } else {
        echo '<h3>' . get_string('examsfound', 'local_examreport') . '</h3>';
        echo '<div class="list-group mb-4">';
        foreach ($quizzes as $q) {
            $url = new moodle_url('/local/examreport/report.php', ['quizid' => $q->id, 'date' => $dateparam]);
            $is_finished = local_examreport_is_quiz_fully_finished($q->id, $dateparam);
            
            if ($is_finished) {
                // Green Icon (All finished)
                $icon = $OUTPUT->pix_icon('i/grade_correct', get_string('finished', 'local_examreport'), 'core', ['class' => 'text-success mr-2']);
            } else {
                // Red Icon (In progress attempts exist)
                $icon = $OUTPUT->pix_icon('i/grade_incorrect', get_string('inprogress', 'local_examreport'), 'core', ['class' => 'text-danger mr-2']);
                // Alternative if grade_incorrect is not desired: 'i/risk_x', 't/stop', etc.
            }

            // Preview link (mod/quiz/report.php?id=cmid&mode=overview)
            // Using cmid fetched from locallib (requires cm.id)
            $previewurl = new moodle_url('/mod/quiz/report.php', ['id' => $q->cmid, 'mode' => 'overview']);
            $previewbtn = '<a href="' . $previewurl . '" target="_blank" class="btn btn-sm btn-info float-right text-white ml-2">' 
                        . get_string('preview', 'local_examreport') . '</a>';

            echo '<div class="list-group-item list-group-item-action">';
            echo $previewbtn;
            echo '<a href="' . $url . '" target="_blank" class="text-dark" style="text-decoration:none;">';
            echo $icon . ' <strong>' . s($q->categoryname) . ' - ' . s($q->coursename) . '</strong>: ' . s($q->name);
            echo '</a>';
            echo '</div>';
        }
        echo '</div>';
    }
}

// 3. (Inline table removed, now handled by report.php)

echo $OUTPUT->footer();
