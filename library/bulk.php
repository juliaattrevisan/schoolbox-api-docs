<?php

class Bulk {

	/**
	 * Parses and adds data from csv file. Returns a list of users and their
	 * add/update outcome, along with any relevant errors.
	 *
	 * CSV fields
			0. username
			1. title
			2. firstname
			3. surname
			4. synergetic
			5. group
			6. campus
			7. campus 2
			8. campus 3
			9. year
			10. password
			11. webmail
			12 alt. email
	 *
	 * @param <type> $file
	 */
	public function addUsers($file) {
		$userHandler = new User();

		/* List of outcomes for each user in the csv
		 */
		$output = array();

		if (is_file($file)) {

			/* Process rows
			 */
			$handle = fopen($file, 'r');
			$headerRow = fgetcsv($handle, 1000, ",");

			while (($user = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $user = array_map('trim', $user);

				/* Only add someone if they have a username and a name
				 */
				if ($user[0] != "" && ($user[2] != "" || $user[3] != "")) {

					/* User groups (student, teacher, etc)
					 */
					$user[5] = is_numeric($user[5]) ? $user[5] : $userHandler->getGroupID($user[5]);

					if (!$user[5]) {
						$output[] = array(
							'username' => $user[0],
							'status' => 'Not Added',
							'error' => 'Unknown Group',
						);

						continue;
					}

					/* Get campus id and group
					 */
					$userCampus = array();

					if ($user[6]) {
						$campus = $userHandler->getCampusID($user[6]);

						if (is_numeric($campus)) {
							$userCampus[] = $campus;
						}
					}

					if ($user[7]) {
						$campus = $userHandler->getCampusID($user[7]);

						if (is_numeric($campus)) {
							$userCampus[] = $campus;
						}
					}

					if ($user[8]) {
						$campus = $userHandler->getCampusID($user[8]);

						if (is_numeric($campus)) {
							$userCampus[] = $campus;
						}
					}

					/* New User object
					 */
					$newUser = array(
						'username' => $user[0],
						'title' => $user[1],
						'firstname' => $user[2],
						'lastname' => $user[3],
						'synergy_id' => $user[4],
						'group_id' => $user[5],
						'campus' => $userCampus,
						'year' => $user[9],
						'password' => $user[10],
						'local_email' => $user[11],
						'alt_email' => $user[12],
					);

					$result = $userHandler->create($newUser);

					/* Adding failed
					 * Check username and synergy already exists
					 */
					if (!$result) {
						$query = "SELECT id FROM user WHERE username = '".mysql_real_escape_string($newUser['username'])."' AND synergy_id = '{$newUser['synergy_id']}'";

						$updateUser = $userHandler->db->getRow($query);

						if (is_array($updateUser)) {
							$newUser['id'] = $updateUser['id'];

							$userHandler->update($newUser);

							$output[] = array(
								'username' => $newUser['username'],
								'status' => 'Updated user',
								'error' => 'OK',
							);
						}
						else {
							$output[] = array(
								'username' => $newUser['username'],
								'status' => 'Not Added',
								'error' => 'Collision',
							);
						}
					}
					else {
						$output[] = array(
							'username' => $newUser['username'],
							'status' => 'Added',
							'error' => 'OK',
						);
					}
				}
                else {
					$output[] = array(
						'username' => $newUser['username'] . ' (' . $newUser['firstname'] . ' ' . $newUser['lastname'] . ')',
						'status' => 'Not Added',
						'error' => 'Insufficient data',
					);
				}
			}
		}

		return $output;
	}

	/**
	 * Remove a list of usernames, or synergy ID's
	 *
	 * @param <type> $file
	 */
	public function removeUsers($file) {
		$userHandler = new User();

		/* List of outcomes for each user in the csv
		 */
		$output = array();

		if (is_file($file)) {

			/* Process rows
			 */
			$handle = fopen($file, 'r');
			$headerRow = fgetcsv($handle, 1000, ",");

			while (($thisRow = fgetcsv($handle, 1000, ",")) !== FALSE) {
				if ($thisRow[0] != "") {

					/* If transfer info is included use that otherwise just use default
					 */
					if ($thisRow[1]) {
						$trans = $thisRow[1];
					}
					else {
						$trans = 1;
					}

					/* If synergy id delete as id
					 */
					if (is_numeric($thisRow[0])) {
						if ($userHandler->deleteSynUser($thisRow[0], $trans)) {
							$output[] = array(
								'username' => $thisRow[0],
								'status' => 'Removed',
								'error' => 'OK',
							);
						}
						else {
							$output[] = array(
								'username' => $thisRow[0],
								'status' => 'Warning',
								'error' => 'Did not delete via Synergy',
							);
						}
					}
					else {
						/* Try username
						 */
						$user = $userHandler->getByUsername($thisRow[0]);

						if ($user) {
							$userHandler->delete($user['uid'], $trans);

							$output[] = array(
								'username' => $thisRow[0],
								'status' => 'Removed',
								'error' => 'OK',
							);
						}
						else {
							$output[] = array(
								'username' => $thisRow[0],
								'status' => 'Not Removed',
								'error' => "Could not find user",
							);
						}
					}
				}
			}
		}

		return $output;
	}

	/**
	 * Adds categories via csv
	 *
	 * CSV Fields:
	 * 0.	parent
	 * 1.	title
	 * 2-n. codes
	 */
	public function addCategories($file, $templateID) {
		$categoryHandler = new Category();

		/* List of outcomes
		 */
		$output = array();

		if (is_file($file)) {
			$groups = array_map(array(Scrub, 'text'), $_POST['groupPerm']);

			/* Process rows
			 */
			$handle = fopen($file, 'r');
			$headerRow = fgetcsv($handle, 1000, ",");

			while (($category = fgetcsv($handle, 1000, ",")) !== FALSE) {
				if (($category[0] != '') && ($category[1] != '')) {

					/* Check parent exists
					 */
					if ($categoryHandler->getByID($category[0]) == FALSE) {
						$output[] = array(
							'category' => $category[1],
							'status' => 'Not Added',
							'error' => "Could not find parent folder {$category[0]}",
						);

						continue;
					}

					/* Check if subject code already exists
					 */
					$codeExists = false;
					$code = array();

					for ($i=2; $category[$i]; $i++) {
						if ($categoryHandler->getByCode($category[$i])) {
							$output[] = array(
								'category' => $category[$i],
								'status' => 'Skipped',
								'error' => "Subject code already exists ({$category[$i]})",
							);

							$codeExists = true;
						}

						$code[] = $category[$i];
					}

					if ($codeExists) {
						continue;
					}

					$newCat = array(
						'name' => $category[1],
						'parent' => $category[0],
						'template' => $templateID,
						'group' => $groups,
						'code' => implode(',', $code),
					);

					/* Successfully created
					 */
					if ($categoryHandler->create($newCat)) {
						$output[] = array(
							'category' => $category[1],
							'status' => 'Added',
							'error' => 'OK',
						);
					}
				}
			}
		}

		return $output;
	}

	/**
	 * Wrapper for uploading a calendar file which could be in either format
	 *
	 * @param  $file
	 * @return array
	 */
	public function addCalendar($file) {
		if (stristr(file_get_contents($file, NULL, NULL, 0, 20), 'BEGIN:VCALENDAR')) {
			return $this->addVCalCalendar($file);
		}
		else {
			return $this->addCSVCalendar($file);
		}
	}

	/**
	 * Imports events from csv
	 *
	 * @param <type> $file
	 * @return array
	 */
	public function addCSVCalendar($file) {
		$calendarHandler = new Calendar();

		/* List of outcomes
		 */
		$output = array();

		if (is_file($file)) {

			/* Set defaults for id and campus if they are not submitted
			 */
			if ($_POST['userid'] == '') {
				$uid = Scrub::text($_SESSION['id']);
			}
			else {
				$uid = Scrub::text($_POST['userid']);
			}

			/* Set up specified campus list
			 * Set up specified group list
			 */
			$groupList = array();
			$campusList = array();

			if (is_array($_POST['userGroup'])) {
				$groupList = array_map(array(Scrub, 'text'), $_POST['userGroup']);
			}

			if (is_array($_POST['campusList'])) {
				$campusList = array_map(array(Scrub, 'text'), $_POST['campusList']);
			}

			/* Process rows
			 */
			$handle = fopen($file, 'r');
			$headerRow = fgetcsv($handle, 1000, ",");

			while (($thisRow = fgetcsv($handle, 1000, ",")) !== FALSE) {

				/* Skip empty comments and no dates
				 *
				 * If access is 3, then the sid corresponds to campus id's
				 * If access is 2, then the sid corresponds to group id's
				 */
				$date = new Alaress_Date_Format(Scrub::html($thisRow[0]), 'd/m/y');

				if ((trim($thisRow[0]) != '') && (trim($thisRow[3]) != '')) {
					$event = array(
						'owner' => $uid,
						'access' => 3,
						'date' => $date->getDatabase(),
						'startTime' => Scrub::html($thisRow[1]),
						'endTime' => Scrub::html($thisRow[2]),
						'note' => Scrub::html($thisRow[3]),
						'detail' => Scrub::html($thisRow[4]),
						'location' => Scrub::html($thisRow[5]),
						'type' => Scrub::html($thisRow[6]),
						'campusList' => $campusList,
						'groupList' => $groupList,
					);

					if ($calendarHandler->create($event)) {
						$output[] = array(
							'name' => Scrub::html($thisRow[3]),
							'status' => 'Added',
							'error' => 'OK',
						);
					}
					else {
						$output[] = array(
							'name' => Scrub::html($thisRow[3]),
							'status' => 'Not Added',
							'error' => 'Could not create event',
						);
					}
				}
				else {
					$output[] = array(
						'name' => Scrub::html($thisRow[3]),
						'status' => 'Not Added',
						'error' => 'Missing date and/or name',
					);
				}
			}
		}

		return $output;
	}

	/**
	 * Imports from a vcal file, exported from outlook
	 *
	 * @param <type> $file
	 */
	public function addVCalCalendar($file) {
		require_once(dirname(__FILE__) .'/iCalcreator.class.php');

		$calendarHandler = new Calendar();
		$v = new vcalendar();

		/* List of outcomes
		 */
		$output = array();

		if (is_file($file)) {
			if ($_POST['userid'] == '') {
				$uid = Scrub::text($_SESSION['id']);
			}
			else {
				$uid = $_POST['userid'];
			}

			/* Set up specified campus list
			 * Set up specified group list
			 */
			$groupList = array();
			$campusList = array();

			if (is_array($_POST['userGroup'])) {
				$groupList = array_map(array(Scrub, 'text'), $_POST['userGroup']);
			}

			if (is_array($_POST['campusList'])) {
				$campusList = array_map(array(Scrub, 'text'), $_POST['campusList']);
			}

			/* Process vcal events
			 */
			$v->setConfig('unique_id', Cfg::get('mail_address'));
			$v->setProperty('calscale', 'GREGORIAN');
			$v->setProperty('method', 'PUBLISH');
			$v->setProperty('X-WR-TIMEZONE', 'Australia/Melbourne');

			date_default_timezone_set('Australia/Melbourne');

			/* Start parse of local file
			 */
			$v->parse($file);

			$eventArray = $v->selectComponents(date('Y'), 01, 01, date('Y')+1, 12, 31);

			foreach($eventArray as $year => $yearArray) {
				foreach($yearArray as $month => $monthArray) {
					foreach($monthArray as $day => $dailyEventsArray) {
						foreach($dailyEventsArray as $vevent) {
							$currddate = $vevent->getProperty('x-current-dtstart');

							/* if member of a recurrence set,
							 * returns array( 'x-current-dtstart', <DATE>)
							 * <DATE> = (string) date("Y-m-d [H:i:s][timezone/UTC offset]")
							 */
							$dtstart = $vevent->getProperty('dtstart');
							$duration = $vevent->getProperty('duration');
							$dtend = $vevent->getProperty('dtend');

							/* dtstart required, one occurence, (orig. start date)
							 */
							$summary = $vevent->getProperty('summary');
							$description = $vevent->getProperty('description');
							$location = $vevent->getProperty('location');

							/* Start times
							 */
							$hour = $dtstart['hour'] ? $dtstart['hour'] : '00';
							$min = $dtstart['min'] ? $dtstart['min'] : '00';
							$stime = strtotime($dtstart['year'] . $dtstart['month'] . $dtstart['day'] . 'T' . $hour . $min . '00Z');

							/* End times
							 */
							$hour = $dtend['hour'] ? $dtend['hour'] : '00';
							$min = $dtend['min'] ? $dtend['min'] : '00';
							$etime = strtotime($dtend['year'] . $dtend['month'] . $dtend['day'] . 'T' . $hour . $min . '00Z');

							$event = array(
								'owner'			=> $uid,
								'access'		=> 3,
								'groupList'		=> array_map(array(Scrub, 'text'), $groupList),
								'campusList'	=> array_map(array(Scrub, 'text'), $campusList),
								'type'			=> 1,
								'note'			=> $summary,
								'detail'		=> $description,
								'location'		=> $location,
								'startTime'		=> isset($dtstart['hour']) ? date('H:i:s', $stime) : '',
								'endTime'		=> isset($dtend['hour']) ? date('H:i:s', $etime) : '',
								'date'			=> date('Y-m-d', $stime),
							);

							if ($calendarHandler->create($event)) {
								$output[] = array(
									'name' => $summary,
									'status' => 'Added',
									'error' => 'OK',
								);
							}
							else {
								$output[] = array(
									'name' => $summary,
									'status' => 'Not Added',
									'error' => 'Could not create event',
								);
							}
						}
					}
				}
			}
		}

		return $output;
	}
}

