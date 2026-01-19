<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) { // Needs this check or logic to add to reports
    
    // Create the admin page
    $ADMIN->add('reports', new admin_externalpage(
        'local_examreport',
        get_string('pluginname', 'local_examreport'),
        new moodle_url('/local/examreport/index.php')
    ));
}
