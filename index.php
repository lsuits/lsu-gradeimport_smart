<?php

require_once('../../../config.php');
require_once('forms.php');
require_once('lib.php');

$_s = function($key, $a=NULL) { return get_string($key, 'gradeimport_smart', $a); };

$id = required_param('id', PARAM_INT);

$url = new moodle_url('/grade/import/smart/index.php', array('id' => $id));
$PAGE->set_url($url);

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('nocourseid');
}

require_login($course);

$context = context_course::instance($id);

$PAGE->set_context($context);

require_capability('moodle/grade:import', $context);
require_capability('gradeimport/smart:view', $context);

$file_text = optional_param('file_text', null, PARAM_TEXT);

print_grade_page_head($course->id, 'import', 'smart');

$file_form = new smart_file_form();
$results_form = new smart_results_form(null, array('messages' => null));

if ($form_data = $file_form->get_data()) {
    $file_text = $file_form->get_file_content('userfile');
    $grade_item_id = $form_data->grade_item_id;

    $messages = array();

    $import_success = true;

    if (!$smart_file = smart_autodiscover_filetype($file_text)) {
        $messages[] = $_s('file_not_identified');
        $import_success = false;
    }

    if ($import_success) {
        $smart_file->validate();
        $smart_file->extract_data();
        $smart_file->set_courseid($id);
        $smart_file->set_gi_id($grade_item_id);
        $smart_file->convert_ids();

        if (!$smart_file->insert_grades()) {
            $messages[] = $_s('import_error');
            $import_success = false;
        }

        if ($smart_file->bad_lines) {
            foreach ($smart_file->bad_lines as $n => $line) {
                $messages[] = $_s('bad_line', $n);
            }
        }

        if ($smart_file->bad_ids) {
            foreach ($smart_file->bad_ids as $userid) {
                $messages[] = $_s('bad_userid', $userid);
            }
        }
    }

    if (!$import_success) {
        echo $OUTPUT->notification($_s('failure'));
    } else {
        echo $OUTPUT->notification($_s('success'), 'notifysuccess');
    }

    $data = array('messages' => $messages);

    if ($messages) {
        $results_form = new smart_results_form(null, $data);
        $results_form->display();
    }

    $url = new moodle_url('/grade/index.php', array('id' => $id));
    echo $OUTPUT->continue_button($url);
} else {
    $file_form->display();
}

echo $OUTPUT->footer();
