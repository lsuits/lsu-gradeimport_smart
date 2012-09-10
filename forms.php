<?php

require_once($CFG->libdir.'/formslib.php');

require_once('lib.php');

class smart_file_form extends moodleform {
    function definition() {
        global $COURSE;

        $_s = function($key) { return get_string($key, 'gradeimport_smart'); };

        $mform =& $this->_form;

        $mform->addElement('header', 'general', $_s('upload_file'));

        $mform->addElement('hidden', 'id', $COURSE->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('filepicker', 'userfile', $_s('file'));
        $mform->addRule('userfile', null, 'required');

        $options = $this->get_grade_item_options();

        $mform->addElement('select', 'grade_item_id', $_s('grade_item'), $options);

        $this->add_action_buttons(false, $_s('upload_file'));
    }

    function get_grade_item_options() {
        global $COURSE, $DB;

        $_s = function($key) { return get_string($key, 'gradeimport_smart'); };

        $params = array('courseid' => $COURSE->id, 'locked' => False);

        $items = $DB->get_records('grade_items', $params, 'itemname asc',
            'id, gradetype, itemname, itemtype');

        $options = array();

        foreach ($items as $n => $item) {
            if ($item->itemtype == 'manual' and $item->gradetype > 0) {
                $options[$item->id] = $item->itemname;
            }
        }

        return $options;
    }
}

class smart_results_form extends moodleform {
    function definition() {
        global $COURSE;

        $_s = function($key) { return get_string($key, 'gradeimport_smart'); };

        $mform =& $this->_form;

        $mform->addElement('header', 'general', $_s('import_notices'));

        $data = $this->_customdata;

        $messages = isset($data['messages']) ? $data['messages'] : null;

        if (is_array($messages)) {
            foreach (array_unique($messages) as $message) {
                $mform->addElement('static', '', '', $message);
            }
        }
    }
}
