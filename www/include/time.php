<?php

/* Some random time helper functions */

function checkTime($hour, $minute)
{
	if ($hour < 0 || $hour > 12 || $minute < 0 || $minute >= 60 )
		error(9, "Invalid time selected: $hour:$minute");
}		

function createTime($hour, $minute, $period)
{
	//in this strange world we live in 12:00 am is actually 00:00 not 12:00 and 12:00 pm is actually 12:00 no 24:00 or 0:00  
	if($period == 'am') //am time period
	{
		$time = ($hour%12).":".$minute.":00";
	}
	else if($period == 'pm') //pm period
	{

		$time = (($hour%12)+12).":".$minute.":00";
	}
	else
		error(9, "Invalid time selected");
	
	return $time;
}

//converts seconds to human readable version to nearest whole
function timeToString($second)
{
	if($second < 0)
		$second = $second * -1;

	if($second < 60) //second
		return $second.' seconds';
	if($second < 3600) //minutes
		return round($second / 60).' minutes';
	if($second < 86400) //hours
		return round($second /3600). ' hours';
	else
		return round($second / 86400).' days';
}