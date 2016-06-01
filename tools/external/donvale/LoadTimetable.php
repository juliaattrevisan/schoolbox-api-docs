<?php
require_once '../../../library/Bootstrap.php';

if ( php_sapi_name() != 'cli' ) {
	die("\nThis script must be run through a command line interface");
}

/* Create file sockets */
$timetableFilePath = $argv[1];
$timetableFileName = basename($timetableFilePath);

if ( @!$timetableFileResource = fopen($timetableFilePath, 'r') ) {
	die("Timetable file could not be loaded.\n");
}

$userHandler = new User;

$columnList = fgetcsv($timetableFileResource);

$lineCount = 1;

$timetableColumnList = array('Id', 'ClassCode', 'Day', 'Period', 'Room');

/* Student account format */
if ( !count( array_diff($timetableColumnList, $columnList) ) ) {

	Database::getInstance()->query("TRUNCATE class_period");
	Database::getInstance()->query("TRUNCATE timetable_period");
	Database::getInstance()->query("TRUNCATE timetable_cycle");

	/* Create regular timetable */
	Database::getInstance()->query("
		INSERT INTO timetable_cycle (title, length, regular)
		VALUES ('', 0, 1)
	");

	$cycleId = Database::getInstance()->lastInsertId();

	$cycleLength = 0;

	$sequenceMax = 0;

	$errorCount = 0;

	while ( $propertyList = fgetcsv($timetableFileResource) ) {
		$lineCount++;

		if ( count($propertyList) != count($columnList) ) {
			print("Incorrect number of fields at line #$lineCount in $timetableFileName\n");

			$errorCount++;

			continue;
		}

		foreach ( $propertyList as $propertyIndex => $propertyValue ) {
			$propertyName = $columnList[$propertyIndex];

			$propertyList[$propertyName] = $propertyValue;
		}

		if ( $propertyList['Period'] == 'BS' ) {
			$propertyList['Period'] = 1;
			$propertyList['Title'] = "Before School";
		}
		elseif ( $propertyList['Period'] == 'HG' ) {
			$propertyList['Period'] = 2;
			$propertyList['Title'] = "Home Group";
		}
		elseif ( $propertyList['Period'] == 'AS' ) {
			$propertyList['Period'] = 9;
			$propertyList['Title'] = "After School";
		}
		else {
			$propertyList['Period'] += 2;
			$propertyList['Title'] = "Period {$propertyList['Period']}";
		}

		/* Increase dimensions of timetable */
		if ( $cycleLength < $propertyList['Day'] ) {
			$cycleLength = $propertyList['Day'];
		}

		if ( $sequenceMax < $propertyList['Period'] ) {
			$sequenceMax = $propertyList['Period'];
		}

		/* Find class */
		$classId = Database::getInstance()->query("
			SELECT id
			FROM class
			WHERE code = '{$propertyList['ClassCode']}'
		")->fetchColumn(0);

		if ( !is_numeric($classId) ) {
			print("Class ({$propertyList['ClassCode']}) not found at line #$lineCount in $timetableFileName\n");

			$errorCount++;

			continue;
		}

		/* Find period */
		$periodId = Database::getInstance()->query("
			SELECT id
			FROM timetable_period
			WHERE cycle_id = $cycleId
					AND day = '{$propertyList['Day']}'
					AND sequence = '{$propertyList['Period']}'
		")->fetchColumn(0);

		/* Create period if it doesn't exist */
		if ( !is_numeric($periodId) ) {
			$period = array(
				'cycleId' => $cycleId,
				'title'    => "Period {$propertyList['Period']}",
				'day'      => $propertyList['Day'],
				'sequence' => $propertyList['Period'],
				'usable'   => 1
			);

			Database::getInstance()->prepare("
				INSERT INTO timetable_period (cycle_id, title, day, sequence, usable)
				VALUES (:cycleId, :title, :day, :sequence, :usable)
			")->execute($period);

			$periodId = Database::getInstance()->lastInsertId();
		}

		/* Create relationship */
		$classPeriod = array(
			'classId'  => $classId,
			'periodId' => $periodId
		);

		Database::getInstance()->prepare("
			INSERT INTO class_period (class_id, period_id)
			VALUES (:classId, :periodId)
		")->execute($classPeriod);
	}

	/* Update cycle based on periods */
	$cycle = array(
		'id'     => $cycleId,
		'title'  => "$cycleLength Day Cycle",
		'length' => $cycleLength
	);

	Database::getInstance()->prepare("
		UPDATE timetable_cycle
		SET title = :title, length = :length
		WHERE id = :id
	")->execute($cycle);

	/* Fill remaining periods */
	for ( $i = 1; $i <= $cycleLength; $i++ ) {
		for ( $j = 1; $j <= $sequenceMax; $j++ ) {

			/* Find period */
			$periodId = Database::getInstance()->query("
				SELECT id
				FROM timetable_period
				WHERE cycle_id = $cycleId
					AND day = $i
					AND sequence = $j
			")->fetchColumn(0);

			/* Create period if it doesn't exist */
			if ( !is_numeric($periodId) ) {
				$period = array(
					'cycleId' => $cycleId,
					'title'    => "Period $j",
					'day'      => $i,
					'sequence' => $j,
					'usable'   => 1
				);

				Database::getInstance()->prepare("
					INSERT INTO timetable_period (cycle_id, title, day, sequence, usable)
					VALUES (:cycleId, :title, :day, :sequence, :usable)
				")->execute($period);

				$periodId = Database::getInstance()->lastInsertId();
			}
			
		}
	}

	print("\n$errorCount of $lineCount class to period associations failed.\n");
}

/* Unrecognized format */
else {
	die("Timetable file is not in a recognised format.\n");
}
