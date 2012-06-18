<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $fields = $DB->get_records_menu('user_info_field', null, '', 'id, name');

    if (!empty($fields)) {
        $default = key($fields);

        $settings->add(new admin_setting_configselect('smart_import/keypadprofile',
            get_string('keypadprofile', 'gradeimport_smart'),
            get_string('keypadprofile_help', 'gradeimport_smart'),
            $default, $fields)
        );
    }
}
