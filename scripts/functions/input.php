<?php

define("ERR", "\x1B[1;31m");
define("RST", "\x1B[0;0;0m");
define("GRN", "\x1B[0;36m");
define("PMT", "\x1B[4;36m");


function get_input_nospaces()
{
	while (1)
	{
		$input = trim(fgets(STDIN));
		if ($input == null || $input == "" || preg_match("/\s/", $input))
			echo ERR."Invalid. Please enter a nonempty string with no spaces.\n".RST;
		else
			return $input;
	}
}

function get_input_nonempty()
{
	while (1)
	{
		$input = trim(fgets(STDIN));
		if ($input == null || $input == "")
			echo ERR."Invalid. Please enter a nonempty string.\n".RST;
		else
			return $input;
	}
}


function get_input_or_empty_string()
{
	while (1)
	{
		$input = trim(fgets(STDIN));
		if ($input == null || $input == "")
			return "";
		elseif (preg_match("/\s/", $input))
				echo ERR."Invalid. Please enter a string with no spaces, or empty to keep default.\n".RST;
		else
			return $input;
	}
}

function get_integer($range = null) {
	while (1)
	{
		$input = trim(fgets(STDIN));
		if ($input === null || $input === "" || preg_match("/\./", $input) || !is_numeric($input))
			echo ERR."Invalid. Please enter an integer.\n".RST;
		else if ($range !== null) {
			$min = $range['min'];
			$max = $range['max'];
			if (intval($input) < $min || intval($input) > $max)
				echo ERR."Invalid. Please enter an integer from $min to $max.\n".RST;
			else
				break;
		}
		else
			break;
	}
	return (intval($input));
}

function strint_pad2(int $num) {
	if (strlen("$num") == 1)
		$num = "0$num";
	else
		$num = "$num";
	return ($num);
}

function get_datetime() {
	echo PMT."Enter month (1-12):\n".RST;
	$month = get_integer(array('min' => 1, 'max' => 12));
	echo PMT."Enter day (1-31):\n".RST;
	$day = get_integer(array('min' => 1, 'max' => 31));
	echo PMT."Enter hour (0-23):\n".RST;
	$hour = get_integer(array('min' => 0, 'max' => 23));
	echo PMT."Enter minute (0-59):\n".RST;
	$minute = get_integer(array('min' => 0, 'max' => 59));
	$month = strint_pad2($month);
	$day = strint_pad2($day);
	$hour = strint_pad2($hour);
	$minute = strint_pad2($minute);
	$dt = DateTime::createFromFormat('Y-m-d H:i:s', "2019-$month-$day $hour:$minute:00");
	return ($dt);
}

?>
