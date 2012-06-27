<?php

/* Countdown to specified date
 */
if (strlen($config['date']) && strlen($config['time'])) {
	$deadline = new Alaress_Date_Format(strtotime($config['date'] . ' ' . $config['time']), 'U');
}
/* Default to now if no countdown date is specified
 * i.e. when creating component for the first time
 *
 * Use +1 seconds to offset some js rendering weirdness
 * This way it initialises at 00/00/00/00/00
 */
else {
	$deadline = new Alaress_Date_Format(time()+1, 'U');
}

$this->setVar(array(
	'COMPONENT_NAME' => strlen(trim($config['componentname'])) ? $config['componentname'] : 'Countdown',
	'COMPONENT_CONFIG_TIME' => $config['time'],
	'COMPONENT_CONFIG_DATE' => $config['date'],
	'DEADLINE_YEAR' => $deadline->getUsingFormat('Y'),
	'DEADLINE_MONTH' => $deadline->getUsingFormat('m'),
	'DEADLINE_DAY' => $deadline->getUsingFormat('d'),
	'DEADLINE_HOUR' => $deadline->getUsingFormat('H'),
	'DEADLINE_MIN' => $deadline->getUsingFormat('i'),
	'DEADLINE_SEC' => $deadline->getUsingFormat('s'),
));
