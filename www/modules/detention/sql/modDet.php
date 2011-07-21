<?php

//detention object...allows you to add/modify/delete det table in database
require_once("notify.php");

class det
{
	function det($id = "")
	{
		
		//setup database
		$SQL_db = "detention";
		$this->mySQLdb = new DeprecatedDatabase($SQL_db);
		$this->mySQLdb->dbConnect();

		$this->detDate = "";
		$this->studID = "";
		$this->staffID = "";
		$this->type = "";
		$this->workset = "";
		$this->_arrParms = array( 'status' => '', 'type' => '', 'order' => '', 'where' => '', 'house' => '', 'student' => '', 'staff' => '', 'year' => '', 'limit' => '');
		$this->id = $id;
		if (!empty($this->id)){
			$this->exists = $this->detExists();
		}
	}
	
	function getOrder($ordernum)
	{
		// Mark Parncutt 18 Jan 2005
		// Made this a switch instead of a heap of elseif, elseif, elseif, ...
		// Also set default sorting by date rather than student name
		switch (intval($ordernum)) {
			case 1:
				$where = " ORDER BY house, detDate DESC, studlname, studfname, type ASC";
				break;
			case 2:
				$where = " ORDER BY stafflname, detDate DESC, stafffname, studlname, studfname, type ASC";
				break;
			case 3:
				$where = " ORDER BY det.status, detDate DESC, studlname, studfname, type ASC";
				break;
			case 4:
				$where = " ORDER BY det.type, detDate DESC, studlname, studfname, type ASC";
				break;
			case 5:
				$where = " ORDER BY year, detDate DESC, studlname, studfname, type ASC"; 
				break;
			case 6:
				$where = " ORDER BY studlname, detDate DESC, det.type, studfname, type ASC";
				break;
			default:
				$where = " ORDER BY detDate DESC, studlname, studfname, type ASC";
				break;
		}
		return $where;
	}
	
	function getType($type)
	{
		if ($type == 1)	
			$where = " AND det.type = 'G'";
		else if ($type == 2)
			$where = " AND det.type = 'Y'";
		else if ($type == 3)
			$where = " AND det.type = 'R'";
		else
			$where = "";
		return $where;
	}

	function getDetConfirmed($filter)
	{
		$this->_arrParms = $filter;
		$this->_arrParms['where'] = "AND det.status in (2, 3, 4)";
		return ($this->getDet());
	}

	function getDetNoCancelled($filter)
	{
		$this->_arrParms = $filter;
		$this->_arrParms['where'] = "AND det.status != 8";
		return ($this->getDet());
	}

	function getDetUnconfirmedHoH($filter)
	{
		$this->_arrParms = $filter;
		$this->_arrParms['where'] = "AND det.status = 1";
		return ($this->getDet());
	}

	function getDetUnconfirmedSS($filter)
	{
		$this->_arrParms = $filter;
		$this->_arrParms['where'] = "AND det.status = 9";
		return ($this->getDet());
	}

	function getDetUnconfirmed($filter)
	{
		$this->_arrParms = $filter;
		$this->_arrParms['where'] = "AND det.status = 1";
		return ($this->getDet());
	}

	function getDetNoShow($filter)
	{
		$this->_arrParms = $filter;
		$this->_arrParms['where'] = "AND det.status = 7";
		return ($this->getDet());
	}

	function getDetCancelled($filter)
	{
		$this->_arrParms = $filter;
		$this->_arrParms['where'] = "AND det.status = 8";
		return ($this->getDet());
	}

	function getDetAttended($filter)
	{
		$this->_arrParms = $filter;
		$this->_arrParms['where'] = "AND det.status = 6";
		return ($this->getDet());
	}

	function getDetUpgraded($filter)
	{
		$this->_arrParms = $filter;
		$this->_arrParms['where'] = "AND det.status = 5";
		return ($this->getDet());
	}

	function getDet($arrParms = "")
	{
	 	if ($arrParms)
			$this->_arrParms = $arrParms;
		if ($this->_arrParms['type'])
			$type = $this->getType($this->_arrParms['type']);
			
		$order = $this->getOrder($this->_arrParms['order']);
		$query = "SELECT 
				userstud.house as hid, 
				h.house as house,
				userstud.year,
				det.id, 
				date_format(detdate.detdatetime, '%e/%m/%y') as detDate, 
				date_format(det.allocatedDate, '%e/%m/%y %l:%i:%p') as detCreated,
				det.detDate as dateID,
				det.studID, 
				userstud.firstname as studfname, 
				userstud.lastname as studlname,
				userstaff.firstname as stafffname,
				userstaff.lastname as stafflname,
				det.staffID, 
				det.offence as offid, 
				offence.description as offence,
				det.workset, 
				det.type, 
				det.status as statid, 
				detstat.type as status,
				det.history
			  FROM (det, intranet.user as userstud, intranet.user as userstaff, detdate, offence)
			";
		$query.= " LEFT JOIN intranet.house h ON userstud.house = h.id
			   LEFT JOIN status as detstat ON det.status = detstat.id
			"; 
		$query.= " WHERE userstud.id = det.studID
			   AND userstaff.id = det.staffID 
			   AND det.detdate = detdate.id
			   AND offence.id = det.offence
			 ".$this->_arrParms['where'];
		$where = "";
		if ($this->id){
			$where.=" AND det.id = '".$this->id."'";
		}
		if ($this->_arrParms['type']){
			$where.= $type;
		}
		if ($this->_arrParms['house']){
			$where.= " AND userstud.house = ".$this->_arrParms['house']; 
		}
		if ($this->_arrParms['student']){
			$studnamearr = explode(" ", $this->_arrParms['student']);
			if (count($studnamearr) == 1){
				$where.= " AND (userstud.firstname like '".$studnamearr[0]."%' || userstud.lastname like '".$studnamearr[0]."%')";
			}
			else {
				$where.= " AND ( 
					(userstud.firstname like '".$studnamearr[0]."%' && userstud.lastname like '".$studnamearr[1]."%') ||
					(userstud.firstname like '".$studnamearr[1]."%' && userstud.lastname like '".$studnamearr[0]."%') 
				)";
			}
		}
		if ($this->_arrParms['staff']){
			$staffnamearr = explode(" ", $this->_arrParms['staff']);
			if (count($staffnamearr) == 1){
				$where.= " AND (userstaff.firstname like '".$staffnamearr[0]."%' || userstaff.lastname like '".$staffnamearr[0]."%')";
			}
			else {
				$where.= " AND ( 
					(userstaff.firstname like '".$staffnamearr[0]."%' && userstaff.lastname like '".$staffnamearr[1]."%') ||
					(userstaff.firstname like '".$staffnamearr[1]."%' && userstaff.lastname like '".$staffnamearr[0]."%') 
				)";
			}
		}
		if ($this->_arrParms['year']){
			$where.= " AND userstud.year = '".$this->_arrParms['year']."'
			";
		}
		if ($this->_arrParms['offence']){
			$where.= " AND offence.id = '".$this->_arrParms['offence']."'
			";
		}
		//if date specified
		if ($this->_arrParms['timeframe'] == 1 && $this->_arrParms['sdate'] && $this->_arrParms['edate']){
			$where.= " AND detdate.detdatetime > '".$this->_arrParms['sdate']." 00:00:00' and detdate.detdatetime < '".$this->_arrParms['edate']." 00:00:00'
			"; 
		}
		//if timeframe is 'to date'
		else if ($this->_arrParms['timeframe'] == 2) {
			$where.= " AND detdate.detdatetime <= now()";
		} 
		//if timeframe is 'weekly' 
		else if ($this->_arrParms['timeframe'] == 3) {
			$sd = mktime(0,0,0, date('m'), date('j') - date('w'), date('Y'));
			$ed = $sd + 604800; 
			$where.= " AND unix_timestamp(detdate.detdatetime) >= '".$sd."' and unix_timestamp(detdate.detdatetime) <= '".$ed."'"; 
		}
		//if timeframe is 'year' 
		else if ($this->_arrParms['timeframe'] == 4) {
			$where.= " AND date_format(deutdate.detdatetime, '%Y') = '".date('Y')."'";	
		}

		if ($this->_arrParms['limit']){   // M. Parncutt 19 Jan 2005
			$limit.= " LIMIT ".$this->_arrParms['limit']."
			";
		}
		$query .= $where;
		$query .= $order;
		$query .= $limit;
		$this->whereclause = $where;

	 	$this->mySQLdb->query($query);	   	
		while ($det = $this->mySQLdb->getNextArray())
		{
			$det['offence'] = stripslashes($det['offence']);
			$det['workset'] = stripslashes($det['workset']);
			$det['history'] = stripslashes($det['history']);
			$listOfDets[] = $det;
		}
		return $listOfDets;
	}
	
	function getDetShort()
	{
		$query = "SELECT * FROM det 
			  WHERE staffID = '".$this->staffID."' 
			  AND studID = '".$this->studID."' 
			  AND offence = '".$this->offence."' 
			  AND type = '".$this->type."'
			  AND detDate = '".$this->detDate."'
			  AND status <> 8
		";
		$this->mySQLdb->query($query);
		//if ($this->mySQLdb->countRows() == 0)
		//	return 0;
		//else {
		// 	return $this->mySQLdb->getNextArray();
		//}
	}

	function addDet($detDate, $studID, $staffID, $offence, $type, $history, $workset = "")
	{
		$this->detDate = $detDate;
		$this->studID = $studID;
		$this->staffID = $staffID;
		$this->type = $type;
		$this->workset = $workset;
		$this->history = $history;
		$this->offence = $offence;

		//get rid of line breaks
		$trans = array("\n" => " ", "\r" => " ");
		$workset = strtr($workset, $trans);
		$history = strtr($history, $trans);
		
		$workset = addslashes($workset);
		$history = addslashes($history);
		if ($this->getDetShort() == 0)
		{
			//find dups and send email notification to school steward
			$dup = 0;
			$dupquery = "SELECT * FROM det WHERE det.studID = ".$studID." AND det.status in (2, 3, 4, 1)"; 
			$this->mySQLdb->query($dupquery);
			//if ($this->mySQLdb->countRows($dupquery) > 0){
			//	$dup = 1;
			//}

			// Robert Skillington Jan 2006
			// If anything but a red card, set the detention as confirmed
			$status = ($type == "R")?1:2;

			$query = "INSERT INTO det 
				(detDate, studID, staffID, offence, type, status, history";
			if ($workset == ""){
				$query.=") VALUES ('$detDate', '$studID', '$staffID', '$offence', '$type', '$status', '$history')";
			}
			else {
				$query.=", workset) VALUES ('$detDate', '$studID', '$staffID', '$offence', '$type', '$status', '$history', '$workset')";
			}
			if($this->mySQLdb->query($query)){
				$query = "SELECT description FROM offence 
				  	  WHERE id = '".$offence."'
				";
				$this->mySQLdb->query($query);
				$off = $this->mySQLdb->dbgetNextArray();
				$query = $this->getNotifyDate($detDate);
				$this->mySQLdb->query($query);
				$date = $this->mySQLdb->getNextArray();

				// Robert Skillington Jan 2006
				// If anything but a red card, set the detention as confirmed
				$state = ($type == "R")?"UC":"ADD";

				$notify = new notify($state, $date, $studID, $staffID, stripslashes($off['description']), stripslashes($workset), $type, stripslashes($history));
				if ($notify->mail())
					return 1;
				else
					return 0;
			}
			else 
				error(21);
		}
		else
			error(38);
	}

	function updateDet($detID, $detDate, $studID, $staffID, $offence, $type, $history, $upgrade, $workset = "")
	{
 
		$query = "SELECT status, detDate FROM det WHERE id = '$detID'";
		$this->mySQLdb->query($query);
		$detarr = $this->mySQLdb->getNextArray();

		$status = $detarr['status'];

		if ($status == 7){

			//check date is has been changed then change status to unconfirmed if it has 
			if ($detDate){
				$status = 1;
			}
		}

		//set status to "upgraded" if upgrade button was pushed
		if ($detDate == 0)
			$detDate = $detarr['detDate'];

		$history = addslashes($history);
		$workset = addslashes($workset);

		if ($this->exists){
			if ($status == 5){
				$query = "UPDATE det set history = '$history'";
			}
			else {
				$query = "UPDATE det set detDate = '$detDate', offence = '$offence', type = '$type'";
				if ($workset)
					$query.=", workset = '$workset'";
				if ($history)
					$query.=", history = '$history'";
			}
			$query.=", status = $status";
			$query.= " WHERE id = '$detID'";
			//notify student of modification
			if ($this->mySQLdb->query($query)) {
				//queries to get parameters for notify function
				$query = "SELECT description FROM offence 
					  WHERE id = '".$offence."'
				";
				$this->mySQLdb->query($query);
				$off = $this->mySQLdb->dbgetNextArray();
				$query = $this->getNotifyDate($detDate);
				$this->mySQLdb->query($query);
				$date = $this->mySQLdb->getNextArray();

			 	//only notify student if the detention has already been confirmed and has been modified since confirmation	
				$status = $this->getStatus($detID);
				if ($status != 1 && $status != 5){			
					$notify = new notify("MOD", $date, $studID, $staffID, stripslashes($off['description']), stripslashes($workset), $type, stripslashes($history));
					if ($notify->mail())
						return 1;
					else
						return 0;
				}
				else if ($status == 5){
					$notify = new notify("DEL", $date, $studID, $staffID, stripslashes($off['description']), stripslashes($workset), $type, stripslashes($history));
					if ($notify->mail())
						return 1;
					else
						return 0;

				}
				return 1;
			}
			else
				error(21); 
		}	
		else
			error(38);	
	}

	function confirmDet($type,$permType=4)
	{
		if ($this->exists)
		{

			$studDetails = $this->getDet();
		
			// Mark Parncut 19 Jan 2005
			// Previously, if a HoH approves a detention it will skip straight past
			// the School Steward.  Fixed now.
			if ($permType == 3) {
				$query = "UPDATE det SET status = '9' WHERE id = '".$this->id."'";
				$this->mySQLdb->query($query);
				return 1;   // <-- Don't send the student an email yet, cos we need to wait for the
						    //      School Steward's approval too.

			} elseif ($permType == 4) {
				if ($studDetails[0]['statid'] == 9)
					$query = "UPDATE det SET status = '3' WHERE id = '".$this->id."'";
				else
					$query = "UPDATE det SET status = '4' WHERE id = '".$this->id."'";
			} else {
				error(19);
			}
			$this->mySQLdb->query($query);

			//send email to student
			$this->date = $studDetails[0]['dateID'];
			//get date into array for notify function to deal with 
			$query = $this->getNotifyDate($this->date);
			$this->mySQLdb->query($query);
			$date = $this->mySQLdb->getNextArray();
			$this->studID = $studDetails[0]['studID'];
			$this->staffID = $studDetails[0]['staffID'];
			$this->offence = $studDetails[0]['offence'];
			$this->workset = $studDetails[0]['workset'];
			$this->history = $studDetails[0]['history'];

			$notify = new notify("ADD", $date, $this->studID, $this->staffID, $this->offence, $this->workset, $type, $this->history);
			if ($notify->mail())
				return 1;
			else
				return 0;
		}
	}
	
	function deleteDet()
	{
		if($this->exists)
		{
			$query = "UPDATE det SET status = '8' WHERE id = '".$this->id."'";
			if ( $this->mySQLdb->query($query)) 
			{
				//notify student of cancellation
				$detDetails = $this->getDet();
				$studID = $detDetails[0]['studID'];
				$staffID = $detDetails[0]['staffID'];
				$off = $detDetails[0]['offence'];
				$workset = $detDetails[0]['workset'];
				$type = $detDetails[0]['type'];
				$date = $detDetails[0]['dateID'];
				$history = $detDetails[0]['history'];

 				//get date into array for notify function to deal with
				$query = $this->getNotifyDate($date);
                        	$this->mySQLdb->query($query);
                        	$date = $this->mySQLdb->getNextArray();

				$notify = new notify("DEL", $date, $studID, $staffID, $off, $workset, $type, $history);
				if ($notify->mail())
					return 1;
				else
					return 0;
			}
			return 1;
		}
	}
	
	function getClasses($su)
	{
		$idcompare = "";
		if (!$su) {
			$idcompare = "AND dd.staff = ".$_SESSION['id'];
		}
		$query = "SELECT	dd.id,
					date_format(dd.detdatetime, '%e/%m/%y') as date, 
				 	dd.type,
					l.location,
					staffu.lastname as stafflname,
					staffu.firstname as stafffname
			  FROM		detention.detdate dd
			  LEFT JOIN	intranet.userAccess ua ON ua.uid = dd.staff		
			  LEFT JOIN	intranet.user staffu ON staffu.id = ua.uid 
			  LEFT JOIN 	detention.location l ON dd.location = l.id
			  WHERE		ua.service = 5
			  AND		ua.type >= 2
			  AND		dd.attend = 0
		";

		if (defined('SHOW_CLASS_LISTS'))  // Mark Parncutt 21 Jan 2005
			$query .= "AND		dd.detdatetime > now()";
		else
			$query .= "AND		dd.detdatetime < now()";

		$query .= $idcompare;
		$query .= " ORDER BY dd.detdatetime ASC";
		$this->mySQLdb->query($query);
		while ($class = $this->mySQLdb->getNextArray()){
			$class['location'] = stripslashes($class['location']);
			$class['stafflname'] = stripslashes($class['stafflname']);
			$class['stafffname'] = stripslashes($class['stafffname']);
			$classList[] = $class;
		}
		return $classList;
	}

	function getStudents($classid)
	{
		$query = "SELECT 	dd.id as detDate,
					d.id as detid,
					d.studID,
					stud.firstname as studfname,
					stud.lastname as studlname,
					stud.year,
					dd.staff,
					staff.id as staffID,
					staff.firstname as stafffname,
					staff.lastname as stafflname,
					date_format(dd.detdatetime, '%e/%m/%y') as date, 
					d.workset,
					d.history,
					o.description as offence,
					l.location,
					h.house
			  FROM		detdate dd 
			  LEFT JOIN	det d ON dd.id = d.detDate
			  LEFT JOIN	intranet.user stud ON stud.id = d.studID
			  LEFT JOIN	intranet.user staff ON staff.id = dd.staff
			  LEFT JOIN	location l ON l.id = dd.location
			  LEFT JOIN	offence o ON o.id = d.offence
			  LEFT JOIN	intranet.house h ON h.id = stud.house
			  WHERE		dd.id = '".$classid."'
			  AND		d.status NOT IN (8, 5) 
			";
		$this->mySQLdb->query($query);
		while ($studs = $this->mySQLdb->getNextArray()){
			$studs['location'] = stripslashes($studs['location']);
			$studs['studlname'] = stripslashes($studs['studlname']);
			$studs['studfname'] = stripslashes($studs['studfname']);
			$studs['workset'] = stripslashes($studs['workset']);
			$studs['offence'] = stripslashes($studs['offence']);
			$studs['history'] = stripslashes($studs['history']);
			$studList[] = $studs;
		}
		return $studList;
	}

	function attended($detID, $attend, $reason = "")
	{
		if ($attend)
			$status = 6;
		else 
			$status = 7;
		if ($reason){
			$sel = "  SELECT history
		 		  FROM det
				  WHERE id = '".$detID."'
			";
			$this->mySQLdb->query($sel);
			$history = $this->mySQLdb->getNextArray();
			if ($history['history'] != "")
				$hist = $history['history']."; no show due to ".$reason;
			else
				$hist = "no show due to ".$reason;

			$query = "UPDATE det
				 SET 	status = ".$status.",
					history = '$hist'
			 	 WHERE	id = '".$detID."'
			";
		}
		else{
			$query = "UPDATE det
		  	  	  SET status = '".$status."'
		  	  	  WHERE id = '".$detID."'
			";
		}
	 	$this->mySQLdb->query($query);
		return 1;
	}
	
	function getStatus($detID)
	{
		$query = "SELECT status FROM det where id = $detID";
		$this->mySQLdb->query($query);
		if ($result = $this->mySQLdb->getNextArray())
			return $result['status'];
		else
			return 0;
	}	

	function attendComplete($detDate)
	{
		$query = "UPDATE detdate
			  SET attend = 1
			  WHERE id = '".$detDate."'
		";
		$this->mySQLdb->query($query);
	}

	function getHouseNames($house = false)
	{
		if ($house) {
			$query = "SELECT * ".			// <-- Mark Parncutt 27 Jan 2005
				"FROM intranet.house ".		//      This is used for when a HoH is viewing the
				"WHERE `id` = $house";      //      history page and they should only be able to
											//      see their own house.
		} else {
			$query = "SELECT * ".
				"FROM intranet.house ".
				"WHERE `id` in (1,2,3,4,5,6,7,8,9,10,11,15) ".  // <-- Mark Parncutt 21 Jan 2005
				"ORDER BY `house` ASC";                         //      we only want Senior School houses,
		}													    //      not the Wadhurst ones too.

		$this->mySQLdb->query($query);
		while ($houses = $this->mySQLdb->getNextArray()){
			$houselist[$houses['id']] = $houses['house'];
		}
		return $houselist; 
		
	}

	function detExists($id = "")
	{
		if ($id)
		{
			$this->id = $id;
		}
		$query = "SELECT status FROM det 
			  WHERE  id = '".$this->id."'
		";
		$this->mySQLdb->query($query);
		if ($this->mySQLdb->countRows() > 0)
			return 1;
		else
			return 0;
	}
	
	function getNotifyDate($detDate) 
	{

		$query = "SELECT date_format(d.detdatetime, '%a %D %b') as detdate, date_format(d.detdatetime, '%l:%i%p') as time, u.firstname, u.lastname, l.location 
			  FROM (detdate d, location l) 
			  LEFT JOIN intranet.user u ON u.id = d.staff
			  WHERE d.id = '".$detDate."'
			  AND d.location = l.id
		";
		return $query;
	}
	
	function countDets() {
		// Mark Parncutt 19 Jan 2005
		// Counts the number of dets that match the filter options
		$query = "SELECT COUNT(det.id) AS count
		 FROM (det, intranet.user as userstud, intranet.user as userstaff, detdate, offence)
		 LEFT JOIN intranet.house h ON userstud.house = h.id
		 LEFT JOIN status as detstat ON det.status = detstat.id
		 WHERE userstud.id = det.studID
		 AND userstaff.id = det.staffID 
		 AND det.detdate = detdate.id
		 AND offence.id = det.offence
		".$this->_arrParms['where']."
		".$this->whereclause."";

		$this->mySQLdb->query($query);
		$count = $this->mySQLdb->getNextArray();
		return intval($count['count']);
	}
}
?>
