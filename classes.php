<?php

require_once 'lib.php';
require_once $CFG->dirroot.'/grade/lib.php';

abstract class SmartFileBase {
    // Grade item id we will be mapping these grades to upon insertion
    private $gi_id;

    // Lines of the uplaoded file
    protected $file_contents;

    // Localized string for the name of this file type
    protected $name;

    // Maps either pawsids, lsuids or anon numbers to grades
    public $ids_to_grades = array();

    // Invalid lines in the file
    public $bad_lines = array();

    // Any ids in the uploaded file that did not exist in the course
    public $bad_ids = array();

    // File objects need to keep track of the course id to convert_ids and
    // insert_grades
    protected $courseid;

    // Maps moodle userids to grades
    private $moodle_ids_to_grades = array();

    // Set file name and get file contents in constructor. Also set localized
    // file type name
    // Note: Maple uses a different constructor
    function __construct($file_contents) {
        $this->file_contents = smart_split_file($file_contents);
    }

    public function set_gi_id($gi_id) {
        $this->gi_id = $gi_id;
    }

    public function set_courseid($courseid) {
        $this->courseid = $courseid;
    }

    public function get_name() {
        return $this->name;
    }

    public function get_field() {
        return $this->field;
    }

    // Returns an array of whatever id field is the key of ids_to_grades
    public function get_ids() {
        return array_keys($this->ids_to_grades);
    }

    public function get_keypad_users($roleids, $context) {
        global $DB;

        $strings = function($id) { return "'$id'"; };

        $role_users = get_role_users($roleids, $context, false);
        $role_userids = implode(',', array_keys($role_users));

        $keyids = array_keys($this->ids_to_grades);
        $keys = implode(',', array_map($strings, $keyids));

        $sql = 'SELECT u.*, d.data AS user_keypadid
            FROM {user} u, {user_info_data} d
            WHERE d.userid = u.id
              AND d.fieldid = :fieldid
              AND d.data IN (' . $keys . ')
              AND u.id IN (' . $role_userids . ')';

        $profileid = get_config('smart_import', 'keypadprofile');
        $params = array('fieldid' => $profileid);

        return $DB->get_records_sql($sql, $params);
    }

    // Takes $ids_to_grades and fills $moodle_ids_to_grades.
    public function convert_ids() {
        global $CFG;

        $roleids = explode(',', $CFG->gradebookroles);

        $context = get_context_instance(CONTEXT_COURSE, $this->courseid);

        $moodle_ids_to_field = array();

        // Keypadid temp fix
        if ($this->get_field() == 'user_keypadid') {
            $users = $this->get_keypad_users($roleids, $context);
        } else {
            $users = get_role_users($roleids, $context, false);
        }

        foreach ($users as $k => $v) {
            $field = $this->get_field();
            $moodle_ids_to_field[$k] = $v->$field;
        }

        $ids_only = array_keys($this->ids_to_grades);

        foreach ($moodle_ids_to_field as $k => $v) {
            $found = array_search($v, $ids_only);

            if ($found !== false) {
                $this->moodle_ids_to_grades[$k] = $this->ids_to_grades[$ids_only[$found]];
            }
        }

        foreach ($this->ids_to_grades as $id => $grade) {
            if (!in_array($id, $moodle_ids_to_field)) {
                $this->bad_ids[] = $id;
            }
        }
    }

    // This is called after the filetype is discovered. Every line is
    // individually validated and removed if it doesn't pass.
    public function validate() {
        $line_count = 1;

        foreach ($this->file_contents as $line) {
            if (!$this->validate_line($line)) {
                $this->bad_lines[$line_count] = $line;

                unset($this->file_contents[$line_count - 1]);
            }

            $line_count++;
        }
    }

    public function insert_grades() {
        global $CFG;
        global $USER;

        if (!$this->moodle_ids_to_grades) {
            return false;
        }

        $gi_params = array('id' => $this->gi_id, 'courseid' => $this->courseid);

        if (!$grade_item = grade_item::fetch($gi_params)) {
            return false;
        }

        foreach ($this->moodle_ids_to_grades as $userid => $grade) {
            $params = array('itemid' => $this->gi_id, 'userid' => $userid);

            if ($grade_grade = new grade_grade($params)) {
                $grade_grade->grade_item =& $grade_item;

                if ($grade_grade->is_locked()) {
                    continue;
                }
            }

            $result = $grade_item->update_final_grade($userid, $grade, 'import');
        }

        return true;
    }

    abstract public function validate_line($line);

    abstract protected function extract_data();
}

/**
 * groups clients using the common extractor method below
 * @TODO a few more levels in the class hierarchy here could 
 * really clean things up ...
 */
abstract class SmartFile_GradeEnd_simpleSeparator extends SmartFileBase{
    public $separator;
    
    /**
     * bare generalization
     */
    protected function extract_data() {
        foreach ($this->file_contents as $line) {
            $fields = explode($this->separator, $line);
            $this->ids_to_grades[$fields[0]] = trim(end($fields));
        }
    }
}

abstract class SmartFile_GradeEnd_regexSeparator extends SmartFile_GradeEnd_simpleSeparator{
    /**
     * uses preg_split versus explode
     */
    protected function extract_data() {
        foreach ($this->file_contents as $line) {
            $fields = preg_split($this->separator, $line);
            $this->ids_to_grades[$fields[0]] = trim(end($fields));
        }
    }
}

// Fixed width grade file.
// 89XXXXXXX 100.00
// 89XXXXXXX 090.00
class SmartFileFixed extends SmartFile_GradeEnd_simpleSeparator {
    protected $field = 'idnumber';
    public $separator = ' ';
    
    public function validate_line($line) {
        if (matcher::match('lsuid2',substr($line, 0, 9))) {
            if (strlen(trim($line)) == 16 && count(explode(' ', $line)) == 2) {
                return True;
            }

            return False;
        }

        return False;
    }
}

// Insane Fixed width grade file.
// 89XXXXXXX anything you want in here 100.00
// 89XXXXXXX i mean anything 090.00
// 89XXXXXXX except, and I mean this: more than one comma (,)
class SmartFileInsane extends SmartFile_GradeEnd_simpleSeparator {
    protected $field = 'idnumber';
    public $separator = ' ';
    
    public function validate_line($line) {
        if (matcher::match('lsuid2',substr($line, 0, 9))) {
            if (count(explode(' ', $line)) > 2) {
                if (count(explode(',', $line)) > 2) {
                    return False;
                }
                return True;
            }

            return False;
        }

        return False;
    }

}


// Grade file from the Measurement and Evaluation Center
// XXX89XXXXXXX 100.00
// XXX89XXXXXXX  90.00
class SmartFileMEC extends SmartFile_GradeEnd_simpleSeparator {
    protected $field = 'idnumber';
    public $separator = ' ';
    
    public function validate_line($line) {
        if (smart_is_mec_lsuid(substr($line, 0, 12))) {
            if (count(explode(' ', $line)) >= 2) {
                return True;
            }

            return False;
        }

        return False;
    }
}

// Grade file for LAW students being graded with an anonymous number
// XXXX,100.00
// XXXX, 90.00
class SmartFileAnonymous extends SmartFile_GradeEnd_simpleSeparator {
    protected $field = 'anonymous';
    public $separator = ',';
    
    public function validate_line($line) {
        $fields = array_map('trim', explode(',', $line));
        return smart_is_anon_num($fields[0]) && smart_is_grade($fields[1]) && count($fields) == 2;
    }

}

// Tab-delimited grade file keyed with lsuid that contains extra information
// 89XXXXXXX    F,  L   M   shortname   data    time    XX  XX  100.00
// 89XXXXXXX    F,  L   M   shortname   data    time    XX  XX  90.00
class SmartFileTabLongLsuid extends SmartFile_GradeEnd_simpleSeparator {
    protected $field = 'idnumber';
    public $separator = "\t";
    
    public function validate_line($line) {
        $tabs = explode("\t", $line);
        $n = count($tabs);

        return smart_is_lsuid2($tabs[0]) && smart_is_grade($tabs[$n - 1]) && $n > 2;
    }

}

// Tab-delimited grade file keyed with pawsid 
// pawsid   100.00
// pawsid   90.00
class SmartFileTabShortPawsid extends SmartFile_GradeEnd_simpleSeparator {
    protected $field = 'username';
    public $separator = "\t";
    
    public function validate_line($line) {
        $tabs = explode("\t", $line);

        if (count($tabs) < 2) {
            return false;
        }

        return smart_is_pawsid($tabs[0]) && smart_is_grade($tabs[1]) && count($tabs) == 2;
    }
}

// Tab-delimited grade file keyed with pawsid that contains extra information
// pawsid    F,  L   M   shortname   data    time    XX  XX  100.00
// pawsid    F,  L   M   shortname   data    time    XX  XX  90.00
class SmartFileTabLongPawsid extends SmartFile_GradeEnd_simpleSeparator {
    protected $field = 'username';
    public $separator = "\t";
    public function validate_line($line) {
        $tabs = explode("\t", $line);
        $n = count($tabs);

        return smart_is_pawsid($tabs[0]) && smart_is_grade($tabs[$n - 1]) && $n > 2;
    }
}


// Tab-delimited grade file keyed with lsuid 
// 89XXXXXXX    100.00
// 89XXXXXXX    90.00
class SmartFileTabShortLsuid extends SmartFile_GradeEnd_simpleSeparator {
    protected $field = 'idnumber';
    public $separator = "\t";
    
    public function validate_line($line) {
        $tabs = explode("\t", $line);
        return smart_is_lsuid2($tabs[0]) && smart_is_grade($tabs[1]) && count($tabs) == 2;
    }
}

// Grade file with comma-separated values keyed with pawsid
// pawsid,100.00
// pawsid,90.00
class SmartFileCSVPawsid extends SmartFile_GradeEnd_simpleSeparator {
    protected $field = 'username';
    public $separator = ',';
    
    public function validate_line($line) {
        $fields = array_map('trim', explode(',', $line));

        if (count($fields) < 2) {
            return false;
        }

        return smart_is_pawsid($fields[0]) && smart_is_grade($fields[1]) && count($fields) == 2;
    }
}

// Grade file with comma-separated values keyed with lsuid 
// 89XXXXXXX,100.00
// 89XXXXXXX,90.00
class SmartFileCSVLsuid extends SmartFile_GradeEnd_simpleSeparator {
    protected $field = 'idnumber';
    public $separator = ',';
    
    public function validate_line($line) {
        $fields = array_map('trim', explode(',', $line));
        return smart_is_lsuid2($fields[0]) && smart_is_grade($fields[1]) && count($fields) == 2;
    }
}

// Comma seperated grade file keyed with lsuid that contains extra information
// Must have more than two fields, the first must be idnumber, the last must be grade
// 89XXXXXXX,    F,  L,   M,   shortname,   data,    time,    XX,  XX,  100.00
// 89XXXXXXX,    F,  L,   M,   shortname,   data,    time,    XX,  XX,  90.00
class SmartFileCommaLongLsuid extends SmartFile_GradeEnd_simpleSeparator {
    protected $field = 'idnumber';
    public $separator = ',';
    
    public function validate_line($line) {
        $commas = explode(',', $line);
        $n = count($commas);

        return smart_is_lsuid2($commas[0]) && smart_is_grade($commas[$n - 1]) && $n > 2;
    }
}

// Comma seperated grade file keyed with pawsid that contains extra information
// pawsid,    F,  L,   M,   shortname,   data,    time,    XX,  XX,  100.00
// pawsid,    F,  L,   M,   shortname,   data,    time,    XX,  XX,  90.00
class SmartFileCommaLongPawsid extends SmartFile_GradeEnd_simpleSeparator {
    protected $field = 'username';
    public $separator = ',';
    
    public function validate_line($line) {
        $commas = explode(',', $line);
        $n = count($commas);

        return smart_is_pawsid($commas[0]) && smart_is_grade($commas[$n - 1]) && $n > 2;
    }
}



// Grade file from the Maple software package
// Irrelevant line
// Irrelevant line
// Name, 89XXXXXXX, Grade %, Grade, Weighted %, Blank Field
// Name, 89XXXXXXX, Grade %, Grade, Weighted %, Blank Field
// Irrelevant line
// Irrelevant line
class SmartFileMaple extends SmartFileBase {
    protected $field = 'idnumber';

    function __construct($file_contents) {
        $lines = smart_split_file($this->file_contents);
        $this->file_contents =  array_slice($lines, 2, count($lines) - 4);
    }

    public function validate_line($line) {
        $fields = explode(',', $line);

        return count($fields) == 6 && smart_is_lsuid2($fields[1]) && is_numeric($fields[3]);
    }

    function extract_data() {
        foreach ($this->file_contents as $line) {
            $fields = explode(',', $line);
            $this->ids_to_grades[$fields[1]] = $fields[3];
        }
    }
}


// Grade file with comma-separated values keyed with keypadid
// 170E98,30
// 1718C0,80
class SmartFileKeypadidCSV extends SmartFile_GradeEnd_simpleSeparator {
    protected $field = 'user_keypadid';
    public $separator = ',';
    
    public function validate_line($line) {
        $fields = explode(',', $line);

        return count($fields) == 2 && smart_is_keypadid($fields[0]) && is_numeric($fields[1]);
    }
}


// Grade file with tabbed or spaced values keyed with keypadid
// 170E98  30
// 1718C0  80
class SmartFileKeypadidTabbed extends SmartFile_GradeEnd_regexSeparator {
    protected $field = 'user_keypadid';
    public $separator = '/\s+/';
    
    public function validate_line($line) {
        $fields = preg_split('/\s+/', $line);

        return count($fields) == 2 && smart_is_keypadid($fields[0]) && is_numeric($fields[1]);
    }
}

class matcher{
    static $patterns = array(
        'lsuid2'    => '/^89\d{7}$/',
        'mec_lsuid' => '/^...89\d{7}$/',
        'grade'     => '/^\d{1,3}?$/',
        'anon_num'  => '/^\d{4}$/',
        'pawsid'    => '/^[a-zA-Z0-9\-]{1,16}$/',
        'keypadid'  => '/^[A-Z0-9]{6}$/',
    );
    /**
     * 
     * @param string $key the regex with which to match
     * @param string $string the string to search
     * @return bool
     */
    public static function match($key,$string){
        if(!array_key_exists($key, self::$patterns)){
            throw new moodle_exception('invalid key requested in call to match()');
        }
        return preg_match(self::$patterns[$key], $string);
    }
}

?>
