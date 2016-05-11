<?php

require_once '../../../library/Bootstrap.php';

if ( php_sapi_name() != 'cli' ) {
	die("\nThis script must be run through a command line interface");
}

/* Create file sockets */
$involvementFilePath = $argv[1];
$involvementFileName = basename($involvementFilePath);

if ( @!$involvementFileResource = fopen($involvementFilePath, 'r') ) {
	die("User file could not be loaded.\n");
}

$userHandler = new User;

$columnList = fgetcsv($involvementFileResource);

$lineCount = 1;

$errorCount = 0;

$involvementColumnList = array('Student Code', 'Class Codes');

/* Class format */
if ( !count( array_diff($involvementColumnList, $columnList) ) ) {
	
	while ( $propertyList = fgetcsv($involvementFileResource) ) {
		$lineCount++;

		if ( count($propertyList) != count($columnList) ) {
			print("Incorrect number of fields at line #$lineCount in $involvementFileName\n");

			$errorCount++;

			continue;
		}

		foreach ( $propertyList as $propertyIndex => $propertyValue ) {
			$propertyName = $columnList[$propertyIndex];

			$propertyList[$propertyName] = $propertyValue;
		}

		$involvement = array();

		$user = $userHandler->getBySynergy($propertyList['Student Code']);

		$involvement['userId'] = $user['uid'];

		if ( !is_numeric($involvement['userId']) ) {
			print("User ({$propertyList['Student Code']}) not found at line #$lineCount in $involvementFileName\n");

			$errorCount++;

			continue;
		}

		$involvement['classId'] = Database::getInstance()->query("
			SELECT id
			FROM class
			WHERE code = '{$propertyList['Class Codes']}'
			LIMIT 1
		")->fetchColumn(0);

		if ( !is_numeric($involvement['classId']) ) {
			print("Class ({$propertyList['Class Codes']}) not found at line #$lineCount in $involvementFileName\n");

			continue;
		}

		Database::getInstance()->prepare("
			INSERT INTO class_user (class_id, user_id)
			VALUES (:classId, :userId)
		")->execute($involvement);
	}

	print("\n$errorCount of $lineCount student to class associations failed.\n");

}

/* Unrecognized format */
else {
	die("Class involvement file is not in a recognised format.\n");
}
