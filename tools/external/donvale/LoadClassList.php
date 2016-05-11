<?php

require_once '../../../library/Bootstrap.php';

if ( php_sapi_name() != 'cli' ) {
	die("\nThis script must be run through a command line interface");
}

/* Create file sockets */
$classFilePath = $argv[1];
$classFileName = basename($classFilePath);

if ( @!$classFileResource = fopen($classFilePath, 'r') ) {
	die("User file could not be loaded.\n");
}

$userHandler = new User;

$columnList = fgetcsv($classFileResource);

$lineCount = 1;

$errorCount = 0;

$classColumnList = array('Class Code', 'Subject Code', 'Teacher');

/* Class format */
if ( !count( array_diff($classColumnList, $columnList) ) ) {

	Database::getInstance()->query("TRUNCATE class");

	Database::getInstance()->query("TRUNCATE class_user");

	while ( $propertyList = fgetcsv($classFileResource) ) {
		$lineCount++;

		if ( count($propertyList) != count($columnList) ) {
			print("Incorrect number of fields at line #$lineCount in $classFileName\n");

			$errorCount++;

			continue;
		}

		foreach ( $propertyList as $propertyIndex => $propertyValue ) {
			$propertyName = $columnList[$propertyIndex];

			$propertyList[$propertyName] = $propertyValue;
		}

		$class = array(
			'name' => $propertyList['Class Code'],
			'code' => $propertyList['Class Code'],
		);

		Database::getInstance()->prepare("
			INSERT INTO class (name, code)
			VALUES (:name, :code)
		")->execute($class);

		$class['id'] = Database::getInstance()->lastInsertId();

		/* Add teacher to class */
		$teacherId = Database::getInstance()->query("
			SELECT id
			FROM user
			WHERE username LIKE '{$propertyList['Teacher']}'
		")->fetchColumn(0);

		if ( !is_numeric($teacherId) ) {
			print("Teacher not found ({$propertyList['Teacher']}) at line #$lineCount in $classFileName\n");

			$errorCount++;

			continue;
		}

		$classUser = array(
			'classId' => $class['id'],
			'userId' => $teacherId
		);

		Database::getInstance()->prepare("
			INSERT INTO class_user (class_id, user_id)
			VALUES (:classId, :userId)
		")->execute($classUser);
	}

	print("\n$errorCount of $lineCount classes failed to load properly.\n");
}

/* Unrecognized format */
else {
	die("Class file is not in a recognised format.\n");
}
