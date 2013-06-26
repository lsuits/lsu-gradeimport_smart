<?php

require_once 'classes.php';

// Reads the first line in a file and tries to figure out what kind of grade
// file it is. A new object of the appropriate grade file type is returned.
function smart_autodiscover_filetype($file) {
    $lines = smart_split_file($file);
    $line = $lines[0];

    $filetypes = array(
        'SmartFileKeypadidCSV',
        'SmartFileKeypadidTabbed',
        'SmartFileFixed',
        'SmartFileInsane',
        'SmartFileCommaLongLsuid',
        'SmartFileCommaLongPawsid',
        'SmartFileTabLongLsuid',
        'SmartFileTabLongPawsid',
        'SmartFileTabShortLsuid',
        'SmartFileCSVLsuid',
        'SmartFileMEC',
        'SmartFileTabShortPawsid',
        'SmartFileAnonymous',
        'SmartFileCSVPawsid',
    );
  
    //try the validate_line() method for each
    foreach($filetypes as $ft){
        $obj = new $ft('');
        if($obj->validate_line($line)){
            return new $ft($file);
        }
    }

    if (count($lines) >= 3 && SmartFileMaple::validate_line($lines[2])) {
        return new SmartFileMaple($file);
    }

    return false;
}

// Splits a file into an array of lines and normalize newlines
function smart_split_file($file) {
    // Replace \r\n with \n, replace any leftover \r with \n, explode on \n
    $lines = explode("\n", preg_replace("/\r/", "\n", preg_replace("/\r\n/", "\n", $file)));

    if (end($lines) == '') {
        return array_slice($lines, 0, count($lines) - 1, True);
    } else {
        return $lines;
    }
}

// Checks whether or not a string is a valid LSUID. It must be a nine digit
// digit number that starts with 89 to pass.
function smart_is_lsuid2($s) {
    return preg_match('/^89\d{7}$/', $s);
}

// Checks whether or not a string is a valid MEC LSUID. It must be a twelve digit
// digit number that starts with three digits and has 89.* afterward.
function smart_is_mec_lsuid($s) {
    return preg_match('/^...89\d{7}$/', $s);
}

// Checks whether or not a string is a valid grade. It must be of the form
// NNN.NN, NN.NN, or N.NN to pass.
function smart_is_grade($s) {
    //return preg_match('/^\d{1,3}(.\d{2})?$/', trim($s));
    return preg_match('/^\d{1,3}|[(.\d{1})]|[(.\d{2})]?$/', trim($s));
}

// Checks whether or not a string is a valid anonymous number. It must be of
// the form XXXX to pass.
function smart_is_anon_num($s) {
    return preg_match('/^\d{4}$/', $s);
}

// Checks wheter or not a string is a valid pawsid. It must be 1-16 and contain
// only alphanumeric characters including hyphens.
function smart_is_pawsid($s) {
    return preg_match('/^[a-zA-Z0-9\-]{1,16}$/', $s);
}

function smart_is_keypadid($s) {
    return preg_match('/^[A-Z0-9]{6}$/', $s);
}

?>
