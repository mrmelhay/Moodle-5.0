<?php
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/locallib.php');

admin_externalpage_setup('local_admingrades_manage'); // We will define this capability later or just use context_system

$context = context_system::instance();
require_login();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/admingrades/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_admingrades'));
$PAGE->set_heading(get_string('managegrades', 'local_admingrades'));

// Process Actions
$action = optional_param('action', '', PARAM_ALPHA);

if ($action === 'process') {
    $processed = local_admingrades_process_grades();
    redirect(new moodle_url('/local/admingrades/index.php'), 'Processed ' . $processed . ' records.');
}

if ($data = data_submitted() && confirm_sesskey()) {
    if (isset($data->add)) {
        $userid = required_param('userid', PARAM_INT);
        $score = required_param('score', PARAM_INT);
        $date = required_param('date', PARAM_TEXT); // Y-m-d
        
        local_admingrades_add_record($userid, $score, $date);
        redirect(new moodle_url('/local/admingrades/index.php'), 'Record added.');
    }
}

echo $OUTPUT->header();

// 1. Form to Add Record
echo $OUTPUT->box_start();
echo '<form method="post" action="index.php" class="form-inline">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<div class="form-group mr-2">';
echo '<label for="userid" class="mr-2">' . get_string('userid', 'local_admingrades') . ':</label>';
echo '<input type="number" name="userid" id="userid" class="form-control" required>';
echo '</div>';
echo '<div class="form-group mr-2">';
echo '<label for="score" class="mr-2">' . get_string('score', 'local_admingrades') . ':</label>';
echo '<input type="number" name="score" id="score" class="form-control" required>';
echo '</div>';
echo '<div class="form-group mr-2">';
echo '<label for="date" class="mr-2">' . get_string('date', 'local_admingrades') . ':</label>';
echo '<input type="date" name="date" id="date" class="form-control" value="' . date('Y-m-d') . '" required>';
echo '</div>';
echo '<button type="submit" name="add" class="btn btn-primary">' . get_string('addgrade', 'local_admingrades') . '</button>';
echo '</form>';
echo $OUTPUT->box_end();

// 2. Process Button
$processurl = new moodle_url('/local/admingrades/index.php', ['action' => 'process']);
echo $OUTPUT->single_button($processurl, get_string('processgrades', 'local_admingrades'), 'post');

// 3. List Records
$records = $DB->get_records('admin_grades', null, 'date DESC, id DESC'); // Assuming table exists

$table = new html_table();
$table->head = [
    'ID', 
    get_string('userid', 'local_admingrades'), 
    get_string('score', 'local_admingrades'), 
    get_string('date', 'local_admingrades'), 
    get_string('attempt', 'local_admingrades')
];

foreach ($records as $r) {
    if(isset($r->userid) && $user = $DB->get_record('user', ['id' => $r->userid])) {
        $username = fullname($user) . ' (' . $user->username . ')';
    } else {
        $username = $r->userid;
    }

    $table->data[] = [
        $r->id,
        $username,
        $r->ball, // 'ball' based on user request
        $r->date,
        $r->attempt
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
