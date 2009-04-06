<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/mssql.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/tutorial/library/event.php';

			
		
$page = new SitePage;
$eventHandler = new Event;
$userHandler = new User;
$mssql = new MSSQL;

$query = "SELECT * FROM ".TUTORIAL_db.".tutorialTemp";
$eventList = $eventHandler->db->getAll($query);
if (is_array($eventList)) {
	foreach ($eventList as $event) {
		
		
		$start = strtotime($event['date']);
		
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
					  WHERE ((vCommunityAddressesConstit.ConstitCode='TUTRES2009') OR (vCommunityAddressesConstit.ConstitCode='TUTNONRES2009'))
					  AND Given1 = ".$eventHandler->db->smartQuote($firstname)." AND Surname = ".$eventHandler->db->smartQuote($surname)."";
		$tutor = $mssql->getRow($query);
		
		if ($event['tutor'] == 'Dirk Den Hartog')
			$tutor['synergy_id'] = 20252;
		elseif ($event['tutor'] == 'David Jones')
			$tutor['synergy_id'] = 3596;
		elseif ($event['tutor'] == 'Jeremy Rosen')
			$tutor['synergy_id'] = 20072;	
			
		if (!isset($tutor['synergy_id'])) {
			print $event['tutor']."\n"; //die("{$event['tutor']} not in synergy");
			continue;
		}
		
		$user = $userHandler->getBySynergy($tutor['synergy_id']);
		if (!isset($user['uid'])) {
			print "{$event['tutor']} ({$tutor['synergy_id']}) not in user table\n"; //die("{$event['tutor']} ({$tutor['synergy_id']}) not in user table");
			continue;
		}
		
		$data = array('typeID'		=> 1,
					  'classID'		=> $class['classID'],
					  'name'		=> $class['class'],
					  'code'		=> $class['code'],
					  'location'	=> $event['location'],
					  'start'		=> outputDate("H:i:s", $start),
					  'finish'		=> outputDate("H:i:s", strtotime("+1 hour", $start)),
					  'tutor1ID'	=> $user['uid'],
					  );
		
		// calculate date sequence
		$date = strtotime($event['date'], mktime(0,0,0,4,5,2009));

		$dateArray = array();
		$until = mktime(0,0,0,6,5,2009);						   
		$validRepeat = array('weekly' => '+1 week', 'fortnightly' => '+2 weeks', 'monthly' => '+1 month');
		$repeat = 'weekly';
		if (isset($validRepeat[$repeat]) && $until) {
			$i = 0;
			for ($date; $date <= $until; $date = strtotime($validRepeat[$repeat], $date)) {
				if ($i++ > 100) break;
					
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
				$eventID = $eventHandler->create($data);
				print_r($data);
			} else
				print "skipped {$data['code']} - {$data['name']} on {$data['date']}\n";
		}
		
	}
	
	print "done\n";
}

?>