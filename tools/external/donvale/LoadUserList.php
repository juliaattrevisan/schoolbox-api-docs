<?php

require_once '../../../include/Bootstrap.php';

if ( php_sapi_name() != 'cli' ) {
	die("\nThis script must be run through a command line interface");
}

/* Create file sockets */
$userFilePath = $argv[1];
$userFileName = basename($userFilePath);

if ( @!$userResource = fopen($userFilePath, 'r') ) {
	die("User file could not be loaded.\n");
}

$userHandler = new User;

$columnList = fgetcsv($userResource);

$lineCount = 1;

$errorCount = 0;

$studentColumnList = array('StudId', 'Password', 'StudFirstName', 'StudLastName', 'Guardian1', 'Guardian2', 'StudAddress', 'Guardian1Tel_H', 'Guardian1Tel_W', 'Guardian1Tel_M', 'Guardian2Tel_H', 'Guardian2Tel_W', 'Guardian2Tel_M', 'Guardian1Add', 'Guardian2Add', 'Guardian1Sub', 'Guardian2Sub', 'Guardian1PostC', 'Guardian1Email', 'Guardian2Email', 'Guardian2PostC', 'Guardian1MailAdd', 'Guardian2MailAdd', 'DateofBirth', 'StudTel', 'StudMob', 'Sex', 'Suburb', 'PostCode', 'EnrolmentYear', 'HomeGroup', 'Coordinator', 'AuthGroup', 'PreferredFirstName', 'StudName', 'StudEmail', 'Level', 'House', 'Id', 'ThirdParty_Code', 'ValidSASCode');
$teacherColumnList = array('StaffId', 'Password', 'Teacher', 'PreferredFirstName', 'Title', 'Email', 'Department', 'Phone', 'Location', 'StaffFirstName', 'StaffLastName', 'AuthGroup', 'House', 'Id', 'ThirdParty_Code', 'Salutation', 'StaffMobile');

/* Student account format */
if ( !count( array_diff($studentColumnList, $columnList) ) ) {

	while ( $propertyList = fgetcsv($userResource) ) {
		$lineCount++;

		if ( count($propertyList) != count($columnList) ) {
			print("Incorrect number of fields at line #$lineCount in $userFileName\n");

			$errorCount++;

			continue;
		}

		foreach ( $propertyList as $propertyIndex => $propertyValue ) {
			$propertyName = $columnList[$propertyIndex];

			$propertyList[$propertyName] = $propertyValue;
		}

		if ( !is_numeric($propertyList['Id']) ) {
			print("Nonnumerical ID provided at line #$lineCount in $userFileName\n");

			$errorCount++;

			continue;
		}

		$user = array(
			'synergy_id'   => $propertyList['Id'],
			'username'     => $propertyList['ThirdParty_Code'],
			'password'     => $propertyList['Password'],
			'title'        => '',
			'firstname'    => $propertyList['StudFirstName'],
			'lastname'     => $propertyList['StudLastName'],
			'year'         => $propertyList['Level'],
			'house'        => $propertyList['House'],
			'local_email'  => 0,
			'alt_email'    => $propertyList['StudEmail'],
			'mail_handler' => 0,
			'su'           => 0,
			'eportfolio_enabled' => 1,
		);

		$user['group_id'] = Database::getInstance()->query("
			SELECT id
			FROM groups
			WHERE name LIKE '{$propertyList['AuthGroup']}'
				AND group_type = '".Group::STUDENT_GROUP_TYPE."'
			LIMIT 1
		")->fetchColumn(0);

		$user['campus'] = Database::getInstance()->query("SELECT id FROM luCampus LIMIT 1")->fetchColumn(0);
		$user['defaultCampus'] = $user['campus'];

		if ( $userClean = $userHandler->getBySynergy($user['synergy_id']) ) {
			$user['id'] = $userClean['uid'];

			$userHandler->update($user);
		}
		else {
			$userHandler->create($user);
		}
	}

	print("\n$errorCount of $lineCount students failed to load.\n");
}

/* Teacher account format */
elseif ( !count( array_diff($teacherColumnList, $columnList) ) ) {
	
	while ( $propertyList = fgetcsv($userResource) ) {
		$lineCount++;

		if ( count($propertyList) != count($columnList) ) {
			print("Incorrect number of fields at line #$lineCount in $userFileName\n");

			$errorCount++;

			continue;
		}

		foreach ( $propertyList as $propertyIndex => $propertyValue ) {
			$propertyName = $columnList[$propertyIndex];

			$propertyList[$propertyName] = $propertyValue;
		}

		if ( !is_numeric($propertyList['Id']) ) {
			print("Nonnumerical ID provided at line #$lineCount in $userFileName\n");

			$errorCount++;

			continue;
		}

		$user = array(
			'synergy_id'   => $propertyList['Id'],
			'username'     => $propertyList['ThirdParty_Code'],
			'password'     => $propertyList['Password'],
			'title'        => $propertyList['Title'],
			'firstname'    => $propertyList['StaffFirstName'],
			'lastname'     => $propertyList['StaffLastName'],
			'year'         => 0,
			'house'        => $propertyList['House'],
			'local_email'  => 0,
			'alt_email'    => $propertyList['Email'],
			'mail_handler' => 0,
			'su'           => 0,
			'eportfolio_enabled' => 0,
		);

		$user['group_id'] = Database::getInstance()->query("
			SELECT id
			FROM groups
			WHERE name LIKE '{$propertyList['AuthGroup']}'
				AND group_type = '".Group::STAFF_GROUP_TYPE."'
			LIMIT 1
		")->fetchColumn(0);

		$user['campus'] = Database::getInstance()->query("SELECT id FROM luCampus LIMIT 1")->fetchColumn(0);
		$user['defaultCampus'] = $user['campus'];

		if ( $userClean = $userHandler->getBySynergy($user['synergy_id']) ) {
			$user['id'] = $userClean['uid'];

			$userHandler->update($user);
		}
		else {
			$userHandler->create($user);
		}
	}

	print("\n$errorCount of $lineCount teachers failed to load.\n");
}

/* Unrecognized format */
else {
	die("User file is not in a recognised format.\n");
}
