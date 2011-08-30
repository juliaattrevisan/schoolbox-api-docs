<?php

require_once '../library/synergetic-attendance.php';
require_once '../library/synergetic-timetable.php';

//check if its a weekend.. cant mark roll on weekend
if(date("w") == 0 ||date("w") == 6)
	error(32);

if(!isStaff($_SESSION['gid'])) {
	error(19);
}

if($this->arg[0]) {
	//output class attendance list
	$this->setFile(TEMPLATE_content, 'attendance/studentList.html');
	$this->setTitle('Attendances');
	$this->setBlock(TEMPLATE_content, 'student_list', 'student_list_block');

	$timetableHandler = new SynergeticTimetable;
	$attendanceHandler = new SynergeticAttendance;

	$daynum = $timetableHandler->getDayNumber($_SESSION['campus'][0]);
	$peroidnum = $timetableHandler->getPeriodNumber($this->arg[0], $daynum);

	$userHandler = new User();

	$pageVar = array(	'TEACHERNAME'	=> $userHandler->formatName($_SESSION),
						'DAYNO'			=> 'Day&nbsp;'.$daynum,
						'DATE'			=> date('j/n/Y'),
						'PERNUMBER'		=> $periodnum,
						'SUBCODE'		=> $this->arg[0],
						'PERIOD'		=> '',
						'SUBJECTNAME'	=> $timetableHandler->getSubjectName($this->arg[0]),
					);
	$this->setVar($pageVar);


}
else {
	//output teachers class list
	$this->setFile(TEMPLATE_content, 'attendance/classList.html');
	$this->setTitle('Attendances');
	$this->setBlock(TEMPLATE_content, 'class_list', 'class_list_block');

	$timetableHandler = new SynergeticTimetable;
	$daynum = $timetableHandler->getDayNumber($_SESSION['campus'][0]);

	$userHandler = new User();

	$pageVar = array(	'TEACHERNAME'	=> $userHandler->formatName($_SESSION),
						'DAYNO'			=> 'Day&nbsp;'.$daynum,
						'DATE'			=> date('j/n/Y'),
					);
	$this->setVar($pageVar);

	$subjectList = $timetableHandler->getDaySubjects($_SESSION['sid'], $daynum);

	if(is_array($subjectList)) {
		foreach($subjectList as $subject) {

			$outputSubject = array(	'ROOM'		=> $subject['Room'],
									'DESC'		=> $subject['Description'],
									'PERNO'		=> $subject['PeriodNumber'],
									'CODE'		=> $subject['ClassCode'],
									'CAMPUS'	=> $subject['ClassCampus'],
									'SUBJECTLINK' => 'attendances.php?code='.$desc['ClassCode'],
									);
			$this->setVar($outputSubject);
			$this->parse('class_list_block', 'class_list', true);
		}

	}
	else
		$this->setVar('class_list_block','');
}
?>
