<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/DeprecatedUser.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/mssql.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';



$page = new SitePage;
$eventHandler = new Event;
$userHandler = new DeprecatedUser;
$mssql = new MSSQL;

$query = "SELECT * FROM ".TUTORIAL_db.".tutorialTemp ";
$eventList = $eventHandler->db->getAll($query);
if (is_array($eventList)) {
	foreach ($eventList as $event) {

		$date = strtotime($event['date'], mktime(0,0,0,8,9,2010));

		if ($date < 86400) {
			//print "{$event['name']} - invalid date\n";
			continue;
		}

		$start = strtotime($event['date'] . ' pm');

		// get class
		$query = "SELECT * FROM ".TUTORIAL_db.".luClass WHERE `class` = " . $eventHandler->db->smartQuote($event['name']) . " OR `code` = " .  $eventHandler->db->smartQuote($event['code']);
		$class = $eventHandler->db->getRow($query);

		if (!isset($class['classID'])) {
			die("no class for " . $event['name'] . " / " . $event['code']);
			continue;
		}

		// get tutor
		$names = explode(" ", $event['tutor']);
		$firstname = array_shift($names);
		if ($firstname == 'Hoe')
			$firstname .= ' '.array_shift($names);
		$surname = implode(" ", $names);
		$query = "SELECT ID synergy_id, Title title, Given1 firstname, Surname lastname
					  FROM SynergyOne.dbo.vCommunityAddressesConstit vCommunityAddressesConstit
					  WHERE ((vCommunityAddressesConstit.ConstitCode='TUTRES2010') OR (vCommunityAddressesConstit.ConstitCode='TUTNONRES2010'))
					  AND Given1 = ".$eventHandler->db->smartQuote($firstname)." AND Surname = ".$eventHandler->db->smartQuote($surname)."";
		$tutor = $mssql->getRow($query);

		$event['tutor'] = trim($event['tutor']);

		if ($event['tutor'] == 'Dirk den Hartog')
			$tutor['synergy_id'] = 20252;
        elseif ($event['tutor'] == 'Jess Corbett')
            $tutor['synergy_id'] = 19150;
        elseif ($event['tutor'] == 'Ghazi Ahamat')
            $tutor['synergy_id'] = 19600;
        elseif ($event['tutor'] == 'Alan Horsfall')
            $tutor['synergy_id'] = 7719;
        elseif ($event['tutor'] == 'Fi Belcher')
            $tutor['synergy_id'] = 20868;
        elseif ($event['tutor'] == 'Josh Mendelsohn')
            $tutor['synergy_id'] = 16651;


//		elseif ($event['tutor'] == 'David Jones')
//			$tutor['synergy_id'] = 1008;
//		elseif ($event['tutor'] == 'Jeremy Rosen')
//			$tutor['synergy_id'] = 20072;
//		elseif ($event['tutor'] == 'Ben Dowdle')
//			$tutor['synergy_id'] = 10691;
//		elseif ($event['tutor'] == 'Bei En Zou')
//			$tutor['synergy_id'] = 21191;
//		elseif ($event['tutor'] == 'Ben Bowring')
//			$tutor['synergy_id'] = 19276;
//		elseif ($event['tutor'] == 'Alex Henry')
//			$tutor['synergy_id'] = 21132;
//		elseif ($event['tutor'] == 'Zac Haines')
//			$tutor['synergy_id'] = 10767;
//		elseif ($event['tutor'] == 'Alex Kline')
//			$tutor['synergy_id'] = 9943;
//		elseif ($event['tutor'] == 'Ed Yencken')
//			$tutor['synergy_id'] = 19565;
//		elseif ($event['tutor'] == 'Chris Wareham')
//			$tutor['synergy_id'] = 21532;
//		elseif ($event['tutor'] == 'Tim McEvoy')
//			$tutor['synergy_id'] = 8274;
//		elseif ($event['tutor'] == 'Tom Graham')
//			$tutor['synergy_id'] = 9285;
//		elseif ($event['tutor'] == 'Nik Parkin')
//			$tutor['synergy_id'] = 24358;


//		elseif ($event['tutor'] == 'Katie Keene')
//			$tutor['synergy_id'] = 10643;

//		elseif ($event['tutor'] == 'Kate York')
//			$tutor['synergy_id'] = 24356;
//		elseif ($event['tutor'] == 'Namrata Jhummon-Mahadnac')
//			$tutor['synergy_id'] = 20596;
//		elseif ($event['tutor'] == 'Tom Milburn')
//			$tutor['synergy_id'] = 20395;
//		elseif ($event['tutor'] == 'Mayucca Pandithage')
//			$tutor['synergy_id'] = 10683;
//		elseif ($event['tutor'] == 'TBA')
//			$tutor['synergy_id'] = 0;

		if (!isset($tutor['synergy_id'])) {
			print $event['tutor']." no synergy_id found \n"; //die("{$event['tutor']} not in synergy");
			continue;
		}

		if ($tutor['synergy_id'] > 0) {
			$user = $userHandler->getBySynergy($tutor['synergy_id']);
			if (!isset($user['uid'])) {
				print "{$event['tutor']} ({$tutor['synergy_id']}) not in user table\n"; //die("{$event['tutor']} ({$tutor['synergy_id']}) not in user table");
				continue;
			}
		}

		$startDate = new Alaress_Date_Format($start, 'U');
		$finishDate = new Alaress_Date_Format(strtotime("+1 hour", $start), 'U');

		$data = array('typeID'		=> 1,
					  'classID'		=> $class['classID'],
					  'name'		=> $class['class'],
					  'code'		=> $class['code'],
					  'location'	=> $event['location'],
					  'start'		=> $startDate->getDatabaseTime(),
					  'finish'		=> $finishDate->getDatabaseTime(),
					  'tutor1ID'	=> $user['uid'],
					  );

		// calculate date sequence


		$dateArray = array();
		$until = mktime(12,0,0,10,4,2010);
		$validRepeat = array('weekly' => '+1 week', 'fortnightly' => '+2 weeks', 'monthly' => '+1 month');
		$repeat = 'weekly';
		if (isset($validRepeat[$repeat]) && $until) {
			$i = 0;
			for ($date; $date <= $until; $date = strtotime($validRepeat[$repeat], $date)) {
				if ($i++ > 100) break;

				/* skip mid semester break */
				if ($date >= strtotime('2010-09-18 10am') && $date <= strtotime('2010-10-03 2pm'))
					continue;

				$dateArray[] = date("Y-m-d", $date);
			}

		} else {
			$dateArray[] = date("Y-m-d", $date);
		}

		// get sequence
		$data['sequenceID'] = $eventHandler->getNewSequenceID();

		for ($i = 0; $i < count($dateArray); $i++) {

			$data['date'] = $dateArray[$i];

			// check event doesnt exist
			$query = "SELECT * FROM ".TUTORIAL_db.".`event` WHERE classID = " . $data['classID'] . " AND `date` = '".$data['date']."'";
			$current = $eventHandler->db->getRow($query);
			if (!isset($current['eventID'])) {
				//$eventID = $eventHandler->create($data);
				print_r($data);
			} elseif ($current['start'] != $data['start']) {
			    print "time changed for {$data['code']} - {$data['name']} on {$data['date']}\n";
			} else {
				print "skipped {$data['code']} - {$data['name']} on {$data['date']}\n";
			}
		}

	}

	print "done\n";
}

?>