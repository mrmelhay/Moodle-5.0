<?php
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/locallib.php');

// Parameters.
$quizid = required_param('quizid', PARAM_INT);
$dateparam = optional_param('date', '', PARAM_TEXT);
$sort = optional_param('sort', 'finalgrade', PARAM_TEXT); // Default Grade
$dir = optional_param('dir', 'DESC', PARAM_ALPHA); // Default DESC
$download = optional_param('download', '', PARAM_ALPHA);

$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);
$category = $DB->get_record('course_categories', ['id' => $course->category], '*', MUST_EXIST);

// Context and Page setup.
$context = context_system::instance();
require_login();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/examreport/report.php', ['quizid' => $quizid, 'date' => $dateparam, 'sort' => $sort, 'dir' => $dir]));
$PAGE->set_title(get_string('pluginname', 'local_examreport') . ': ' . $quiz->name);
$PAGE->set_heading($quiz->name);
$PAGE->set_pagelayout('popup');

// Process Data.
$results = local_examreport_get_quiz_results($quizid, $dateparam, $sort, $dir);

// Handle Download.
if ($download === 'pdf') {
    require_once($CFG->libdir . '/pdflib.php');
    
    // Simple PDF Generation
    $pdf = new pdf();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage('L'); // Landscape
    $pdf->SetFont('freeserif', '', 14);
    
    $pdf->Write(0, get_string('course', 'local_examreport') . ': ' . $category->name . ' - ' . $course->fullname . "\n");
    $pdf->Write(0, get_string('quiz', 'local_examreport') . ': ' . $quiz->name . "\n");
    if ($dateparam) {
         $formatteddate = date('d.m.Y', strtotime($dateparam));
         $pdf->Write(0, get_string('date') . ': ' . $formatteddate . "\n");
    }
    $pdf->Write(0, "\n");
    
    $pdf->SetFont('freeserif', '', 10);
    
    // Table Header
    $headers = [
        get_string('username', 'local_examreport'),
        get_string('studentname', 'local_examreport'),
        get_string('email', 'local_examreport'),
        get_string('status', 'local_examreport'),
        get_string('timestart', 'local_examreport'),
        get_string('timefinish', 'local_examreport'),
        get_string('duration', 'local_examreport'),
        get_string('grade', 'local_examreport')
    ];
    
    // Table with styling
    $html = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%; text-align:center;">';
    
    // Column Widths (Sum ~ 100%)
    // ID (8%), FIO (25%), Email (15%), Status (10%), Start (12%), Finish (12%), Duration (10%), Grade (8%)
    $widths = ['8%', '25%', '15%', '10%', '12%', '12%', '10%', '8%'];

    $html .= '<thead style="background-color:#f2f2f2;"><tr>';
    foreach ($headers as $i => $h) {
        $w = isset($widths[$i]) ? $widths[$i] : 'auto';
        $html .= '<th style="font-weight:bold; width:' . $w . ';">' . $h . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    
    foreach ($results as $row) {
        $html .= '<tr nobr="true">';
        $html .= '<td style="width:8%; text-align:center; vertical-align:middle;">' . $row->username . '</td>';
        $html .= '<td style="width:25%; text-align:left; vertical-align:middle;">' . $row->fullname . '</td>';
        $html .= '<td style="width:15%; text-align:center; vertical-align:middle;">' . $row->email . '</td>';
        $html .= '<td style="width:10%; text-align:center; vertical-align:middle;">' . $row->status . '</td>';
        $html .= '<td style="width:12%; text-align:center; vertical-align:middle;">' . $row->timestart . '</td>';
        $html .= '<td style="width:12%; text-align:center; vertical-align:middle;">' . $row->timefinish . '</td>';
        $html .= '<td style="width:10%; text-align:center; vertical-align:middle;">' . $row->duration . '</td>';
        $html .= '<td style="width:8%; text-align:center; vertical-align:middle;">' . $row->grade . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    $pdf->writeHTML($html);

    // ERI Signed & QR Code
    $pdf->Ln(20); // Space after table
    
    // Calculate position for text and QR code
    // Let's place text on left, QR on right or centered.
    // User asked "eng oxiriga" (at the very end).
    
    $y = $pdf->GetY();
    
    // Text
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 10, get_string('erisigned', 'local_examreport'), 0, 1, 'L');
    
    // QR Code
    // Content of QR code: Link to check original document validity. 
    // Using verify.php with token.
    $token = local_examreport_generate_token($quizid, $dateparam);
    $qrcontent = new moodle_url('/local/examreport/verify.php', ['quizid' => $quizid, 'date' => $dateparam, 'token' => $token]);
    $qrcontent = $qrcontent->out(false); // Get raw URL string
    
    // write2DBarcode(code, type, x, y, w, h, style, align, dist)
    $style = array(
        'border' => false,
        'vpadding' => 'auto',
        'hpadding' => 'auto',
        'fgcolor' => array(0,0,0),
        'bgcolor' => false, // array(255,255,255)
        'module_width' => 1, // width of a single module in points
        'module_height' => 1 // height of a single module in points
    );
    // Place QR code below text
    $pdf->write2DBarcode($qrcontent, 'QRCODE,H', $pdf->GetX(), $pdf->GetY(), 30, 30, $style, 'N');
    
    $pdf->Output($category->name. ' - ' . $course->fullname . '.pdf', 'D');
    die();
}

// Start Output.
echo $OUTPUT->header();

echo '<h3>' . get_string('course', 'local_examreport') . ': ' . s($category->name) . ' - ' . s($course->fullname) . '</h3>';
echo '<h4>' . get_string('quiz', 'local_examreport') . ': ' . s($quiz->name) . '</h4>';

$exporturl = new moodle_url('/local/examreport/report.php', ['quizid' => $quizid, 'date' => $dateparam, 'sort' => $sort, 'dir' => $dir, 'download' => 'pdf']);
echo $OUTPUT->single_button($exporturl, 'PDF Export');

$table = new html_table();

// Helper to create sortable link
function local_examreport_sort_link($colname, $currentsort, $currentdir, $quizid, $dateparam) {
    if ($currentsort == $colname) {
        $nextdir = ($currentdir == 'ASC') ? 'DESC' : 'ASC';
        $icon = ($currentdir == 'ASC') ? ' ↓' : ' ↑';
    } else {
        $nextdir = 'ASC';
        $icon = '';
    }
    $url = new moodle_url('/local/examreport/report.php', ['quizid' => $quizid, 'date' => $dateparam, 'sort' => $colname, 'dir' => $nextdir]);
    return '<a href="' . $url . '">' . get_string($colname, 'local_examreport') . $icon . '</a>';
}

$table->head = [
    local_examreport_sort_link('username', $sort, $dir, $quizid, $dateparam),
    local_examreport_sort_link('studentname', $sort, $dir, $quizid, $dateparam), // Note: string key matching lang file
    local_examreport_sort_link('email', $sort, $dir, $quizid, $dateparam),
    get_string('status', 'local_examreport'), // Not sortable for now
    local_examreport_sort_link('timestart', $sort, $dir, $quizid, $dateparam),
    local_examreport_sort_link('timefinish', $sort, $dir, $quizid, $dateparam),
    local_examreport_sort_link('duration', $sort, $dir, $quizid, $dateparam),
    local_examreport_sort_link('grade', $sort, $dir, $quizid, $dateparam), // 'grade' map to 'finalgrade' in locallib
];

$table->data = [];

foreach ($results as $row) {
    // Link ID to Review page
    $reviewurl = new moodle_url('/mod/quiz/review.php', ['attempt' => $row->attemptid]);
    $reviewlink = '<a href="' . $reviewurl . '" target="_blank">' . $row->username . '</a>';

    $table->data[] = [
        $reviewlink,
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
