<?php
$DS = DIRECTORY_SEPARATOR;
global $CFG;
require_once $CFG->dirroot.$DS.'grade'.$DS.'import'.$DS.'smart'.$DS.'classes.php';

class smart_file_line_recognition_testcase extends advanced_testcase{

    public $idnumber;
    
    public function setup(){
        $this->idnumber = smart_is_lsuid2(890000001) ? 890000001 : false;

    }
    
    public function test_SmartFileKeypadidCSV_line_recognizer(){
        $unit = new SmartFileKeypadidCSV('');
        $line = 'ASDF23, 100.0';
        $this->assertTrue($unit->validate_line($line));
    }
    
    public function test_SmartFileKeypadidTabbed_line_recognizer(){
        $unit = new SmartFileKeypadidTabbed('');
        $line = 'ASDF23 100.0';
        $this->assertTrue($unit->validate_line($line));
    }
    
    public function test_SmartFileFixed_line_recognizer(){
        $unit = new SmartFileFixed('');
        $line = $this->idnumber.' 100.00';
        $this->assertTrue($unit->validate_line($line));
    }    
    
    public function  test_SmartFileInsane_line_recognizer(){
        $unit = new SmartFileInsane('');
        $line = '891234567 except, and I mean this: more than one comma...';
        $this->assertTrue($unit->validate_line($line));
    }
    
    public function  test_SmartFileCommaLongLsuid_line_recognizer(){
        $unit = new SmartFileCommaLongLsuid('');
        $line = '891234567, first name, l, xxx, 89.0';
        $this->assertTrue($unit->validate_line($line));
    }
    
    public function  test_SmartFileCommaLongPawsid_line_recognizer(){
        $unit = new SmartFileCommaLongPawsid('');
        $line = 'mtiger1, first name, l, xxx, 89.0';
        $this->assertTrue($unit->validate_line($line));
    }
    
    public function  test_SmartFileTabLongLsuid_line_recognizer(){
        $unit = new SmartFileTabLongLsuid('');
        $line = $this->idnumber."\tfirst name\tl\txxx\t89.0";
        $this->assertTrue($unit->validate_line($line));
    }
    
    public function  test_SmartFileTabLongPawsid_line_recognizer(){
        $unit = new SmartFileTabLongPawsid('');
        $line = "mtiger1\tfirst name\tl\txxx\t89.0";
        $this->assertTrue($unit->validate_line($line));
    }
    
    public function  test_SmartFileTabShortLsuid_line_recognizer(){
        $unit = new SmartFileTabShortLsuid('');
        $line = $this->idnumber."\t89.0";
        $this->assertTrue($unit->validate_line($line));
    }
    
    public function  test_SmartFileCSVLsuid_line_recognizer(){
        $unit = new SmartFileCSVLsuid('');
        $line = $this->idnumber.",89.0";
        $this->assertTrue($unit->validate_line($line));
    }
    
    public function  test_SmartFileMEC_line_recognizer(){
        $unit = new SmartFileMEC('');
        $line = "123".$this->idnumber." mtiger1 89.0";
        $this->assertTrue($unit->validate_line($line));
    }
    
    public function  test_SmartFileTabShortPawsid_line_recognizer(){
        $unit = new SmartFileTabShortPawsid('');
        $line = "mtiger1\t89.0";
        $this->assertTrue($unit->validate_line($line));
    }
    
    public function  test_SmartFileAnonymous_line_recognizer(){
        $unit = new SmartFileAnonymous('');
        $line = "1234, 89.0";
        $this->assertTrue($unit->validate_line($line));
    }
    
    public function  test_SmartFileCSVPawsid_line_recognizer(){
        $unit = new SmartFileCSVPawsid('');
        $line = "mtiger1,89.0";
        $this->assertTrue($unit->validate_line($line));
    }

}
?>
