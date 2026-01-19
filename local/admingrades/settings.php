<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_admingrades_manage',
        get_string('managegrades', 'local_admingrades'),
        new moodle_url('/local/admingrades/index.php')
    ));
}
