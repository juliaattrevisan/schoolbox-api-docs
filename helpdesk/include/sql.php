<?php
# File		: sql.php
# Written	: Nick Doyle, 4/7/2002
# Description	: SQL for helpdesk pages
#		  All database-specific code should be in here

function SQL_jobList( &$parms) {

	if( func_num_args() > 1) {		# using ORs
		$conjunction = ' OR  ';
	}
	else {
		$conjunction = ' AND ';
	}

	$sql = "
        	select  j.jobID, 
			j.portalAssociatedUserID, 
			j.portalAssignedUserID,
			concat(assignedUser.firstname, ' ', assignedUser.lastname) as assignedUserName,
			concat(associatedUser.firstname, ' ', associatedUser.lastname) as associatedUserName,
			concat(equipmentUser.firstname, ' ', equipmentUser.lastname) as equipmentUserName,
			p.description as problem,
			j.description,
			DATE_FORMAT(j.createdDate, '%e/%c/%Y') as createdDate,
			DATE_FORMAT(j.createdDate, '%k:%i') as createdTime,
			s.description as status,
			e.userID,
			e.userNonSynergy,
			pri.colour
        	from    tblJob j
			inner join
			tblProblem p on j.problemID = p.problemID
                        left join
                        tblPriority pri on j.priorityID = pri.priorityID
			inner join
			tblStatus s on j.statusID = s.statusID
			left join
			tblEquipment e on j.equipmentID = e.equipmentID
			left join
			".DB_NAME_PORTAL.".user assignedUser on assignedUser.id = j.portalAssignedUserID
			left join
			".DB_NAME_PORTAL.".user associatedUser on associatedUser.id = j.portalAssociatedUserID 
			left join
			".DB_NAME_PORTAL.".user equipmentUser on equipmentUser.id = e.portalUserID 
        	where ";

	$chopLen = 6;		# number of chars to chop off end of query
	foreach( array_keys( $parms) as $field) {
		$value = $parms[ $field];
		if( isset( $value) and ($value != '') and ($value != 'all')) {
			$chopLen = 5;
			switch( $field) {
				case 'status' :
					switch( $value) {
						case 'open' :
							$sql .= "s.description != 'Closed'" . $conjunction;
							break;
						default :
							$sql .= "s.description = " . sql_escape( $value) . $conjunction;
							break;
					}
					break;
				case 'description' :
					$sql .= "j.description like " . sql_escape( "%" . $value . "%") . " and ";
					break;
				case 'serial' :
					$sql .= "e.serial like " . sql_escape( "%" . $value . "%") . " and ";
					break;
				case 'synergyID' :
					$sql .= "j.associatedUserID =" . sql_escape( $value) . $conjunction;
					break;
				case 'portalID' :
					$sql .= "j.portalAssociatedUserID =" . sql_escape( $value) . $conjunction;
					break;
				case 'equipmentUserID' :
					$sql .= "e.userID =" . sql_escape( $value) . $conjunction;
					break;
				case 'equipmentPortalUserID' :
					$sql .= "e.portalUserID =" . sql_escape( $value) . $conjunction;
					break;
				case 'notAssignedUserID' : //special case to exlude user form list
					$sql .= "j.portalAssignedUserID !=" . sql_escape( $value) . $conjunction;
					break;
				default	:
					$sql .= tableQualifier( $field) . $field . " = " . sql_escape( $value) . $conjunction;
					break;
			}
		}
	}
	$sql = substr( $sql, 0, strlen( $sql) - $chopLen);

	if($parms['portalID'] || $parms['portalAssignedUserID'] || $parms['equipmentPortalUserID'] || $parms['equipmentID'])
		$sql .= ' order by j.createdDate desc';	
	else
		$sql .= ' order by  assignedUser.lastname, j.createdDate desc';
	return $sql;
}

function SQL_eventDelete( $jobEventID) {
	$jobEventID = sql_escape( $jobEventID);
	$sql= " delete
		from	tblJobEvent
		where	jobEventID = " . $jobEventID;
	return $sql;
}

function SQL_eventDetails( $parms) {
	$sql= "	select	jobEventID,
			jobID,
			userID,
			description,
			createdDate,
			eventTypeID
		from	tblJobEvent je
	" .	whereClause( $parms);

	return $sql;
}

function SQL_eventList( &$parms) {
	$sql =" select	distinct
			j.jobID,
			j.jobEventID,
			j.description as jobEventDescription,
			t.description as eventTypeDescription,
			j.userID,
			j.portalUserID,
			concat(u.firstname , ' ', u.lastname) as eventOwnerName,
			DATE_FORMAT(j.createdDate, '%a, %D %b %Y %k:%i') as createdDate
		from	tblJobEvent j
			left join
			tblEventType t on j.eventTypeID = t.eventTypeID
			left join ".DB_NAME_PORTAL.".user u on j.portalUserID = u.id
	";

	if( is_array( $parms)) {
		$sql .= ' where ';
		$parmFlag = 0;

		foreach( array_keys( $parms) as $key) {
			$value = $parms[ $key];
			if( $parms[ $key] <> '') {					# If the parm is set
				$value = $parms[ $key];
				$parmFlag = 1;
				switch( $key) {
					case 'description' :
						$value = sql_escape( "%" . $value . "%");
						$sql .= "t.description like " . $value . " and ";
						break;
					default	:
						$sql .= $key . " = " . sql_escape( $value) . " and ";
						break;
				}
			}

		}
		if( $parmFlag == 1) {
			$sql = substr( $sql, 0, strlen( $sql) - 5); 		# trim off trailing ' AND '
		}
		else {								# trim of trailing ' where ';
			$sql = substr( $sql, 0, strlen( $sql) - 6);
		}
	}
	else { 									# we're gettin ALL events
	}

	$sql .= ' order by j.createdDate';

	return $sql;
}


	# $showUsers is a flag to speed queries - if true, will join on the intranet.user table. Otherwise will not (faster).
	#
function SQL_equipmentList( &$parms, $showUsers) {
	$parmFlag = 0;

	if( func_num_args() > 2) {		# using ORs
		$conjunction = ' OR  ';
	}
	else {
		$conjunction = ' AND ';
	}

		# Array of fields which will be exactly matched (instead of LIKEd)
	$arrExact = array( 'equipmentTypeID', 'brandID', 'modelID', 'active', 'portalUserID' ,'locationID' );

	$sql = '
		select	e.equipmentID,
			e.serial,
			e.active,
			e.equipmentTypeID,
			t.description as equipmentType,
			e.modelID,
			m.brandID,
			m.description as model,
			b.description as brand,	
			e.userID,
			o.description as owner,
	';
	if( $showUsers) {
		$sql .='
			u.firstname as firstname,
			u.lastname as surname,
		';
	}
	$sql .= '
			l.description as location, 
			l.locationID
		from	tblEquipment e
			left join
			tblEquipmentType t on e.equipmentTypeID = t.equipmentTypeID
			left join
			tblModel m on e.modelID = m.modelID
			left join
			tblOwner o on o.ownerID = e.ownerID
			left join
			tblBrand b on m.brandID = b.brandID
			left join
			tblLocation l on e.locationID = l.locationID
	';
	if( $showUsers) {
		$sql .= "
			left join
			".DB_NAME_PORTAL.".user u on (e.portalUserID <> '') and (u.id = e.portalUserID)
		";
	}

		# Add the optional parameters
	if( is_array( $parms)) {
		$sql .= ' where ';
		foreach( array_keys( $parms) as $key) {
			$value = $parms[ $key];
			if(( $parms[ $key] <> '') or ( $parms[ $key] <> 0)) {			# If the parm is set
				$parmFlag = 1;
				if( array_search( $key, $arrExact)) {
					$sql .= tableQualifier( $key) . $key . ' = ' . sql_escape( $value) . $conjunction;
				}
				else {
					$sql .= tableQualifier( $key) . $key . ' like ' . sql_escape( '%' . $value . '%').$conjunction;
				}
			}
		}
		if( $parmFlag == 1) {
			$sql = substr( $sql, 0, strlen( $sql) - 5); 				# trim off trailing ' AND '
		}
		else {										# trim of trailing ' where ';
			$sql = substr( $sql, 0, strlen( $sql) - 6);
		}
	}

	return $sql;
}

function SQL_reportReady(&$parms) {

	$sql= "	select	jobID
		from	tblJob
	";
	$where = whereClause($parms);
	if (strlen($where) == 1)
		$where = " where ";
	else
		$where.=" and ";

	$sql.=  $where." 
		(statusID <> 3
		and latestEvent = 16
		or latestEvent = 8)
	";
	return ($sql);
}
function SQL_reportDealer(&$parms) {

	$sql= "	select
				j.jobID,
				j.description,
				concat(associatedUser.firstname, ' ', associatedUser.lastname) as associatedUserName,
				concat(equipmentUser.firstname, ' ', equipmentUser.lastname) as equipmentUserName,
				e.userNonSynergy as equipmentUserNonSynergy,
				associatedUser.id as portalAssociatedUserID,
				equipmentUser.id as equipmentUserID,
				e.serial as equipmentSerial,
				j.equipmentID,
				concat( b.description, ' ', m.description) as equipmentModel
			from	tblJob j 
				left join 
					tblEquipment e on e.equipmentID = j.equipmentID
				left join
					".DB_NAME_PORTAL.".user associatedUser on associatedUser.id = j.portalAssociatedUserID 
				left join
					".DB_NAME_PORTAL.".user equipmentUser on equipmentUser.id = e.portalUserID 
				left join
					tblModel m on m.modelID = e.modelID
				left join
					tblBrand b on b.brandID = m.brandID ";

	$where = whereClause($parms);
	if (strlen($where) == 1)
		$where = " where ";
	else
		$where.=" and ";

	$sql.= $where."
		statusID <> 3
		and latestEvent = 15";
	return ($sql);
}

function SQL_reportCourier(&$parms) {

	$sql= "	select  
				j.jobID,
				j.description,
				concat(associatedUser.firstname, ' ', associatedUser.lastname) as associatedUserName,
				concat(equipmentUser.firstname, ' ', equipmentUser.lastname) as equipmentUserName,
				e.userNonSynergy as equipmentUserNonSynergy,
				associatedUser.id as associatedUserID,
				equipmentUser.id as equipmentUserID,
				e.serial as equipmentSerial,
				j.equipmentID,
				concat( b.description, ' ', m.description) as equipmentModel
			from	tblJob j 
				left join 
					tblEquipment e on e.equipmentID = j.equipmentID
				left join
					".DB_NAME_PORTAL.".user associatedUser on associatedUser.id = j.portalAssociatedUserID 
				left join
					".DB_NAME_PORTAL.".user equipmentUser on equipmentUser.id = e.portalUserID 
				left join
					tblModel m on m.modelID = e.modelID
				left join
					tblBrand b on b.brandID = m.brandID ";

	$where = whereClause($parms);
	if (strlen($where) == 1)
		$where = " where ";
	else
		$where.=" and ";

	$sql .= $where."
		statusID <> 3
		and latestEvent = 7";
	return ($sql);
}

function SQL_userList( &$parms) {
	if( !is_array( $parms)) {
		die( 'SQL_userList : parms must be supplied in an array. Exiting.');
	}

	if( func_num_args() > 1) {		# using ORs
		$conjunction = ' OR ';
		$closing = '1=0';
	}
	else {
		$conjunction = ' AND ';
		$closing = '1=1';
	}
	
	$where = '';
	$isisis = 0;

	foreach( $parms as $key => $value) 
	{
		$value = $parms[ $key];
		if( $value <> '') 
		{					# If the parm is set
			$value = sql_escape( "%" .  $value . "%");
			$where .= $key . ' like ' . $value .' '. $conjunction;
			$isisis = 1;
		}
	}
	
	//special case if they selecting everything but have used the or conjunction
	if(!$isisis)
		$closing = '1=1';

	$sql= " select	
			user.id as portalID,
			user.synergy_id as synergyID,
			user.firstname,
			user.lastname as surname,
			groups.name as groupname
		from	user
		left join groups on user.group_id = groups.id 
		WHERE $where $closing
		order by surname, firstname
	";
	return $sql;
}

function SQL_dealerList(&$parms)
{
	if( !is_array( $parms)) {
		die( 'SQL_dealerList : parms must be supplied in an array. Exiting.');
	}
	
	if( func_num_args() > 1) {		# using ORs
		$conjunction = ' OR ';
		$closing = '1=0';
	}
	else {
		$conjunction = ' AND ';
		$closing = '1=1';
	}

	$where = '';
	$isisis = 0;
	
	foreach( $parms as $key => $value) 
	{
		if( $value <> '') 
		{					# If the parm is set
			$value = sql_escape( "%" .  $value . "%");
			$where .= $key . ' like ' . $value .' '. $conjunction;
			$isisis = 1;
		}
	}
	
	if(!$isisis)
		$closing = '1=1';
	
	$sql = "SELECT dealer.dealerID, dealer.description, contact.name, contact.pos, contact.email, contact.phoneno 
			FROM tblDealer dealer LEFT JOIN tblDealerContact contact ON dealer.dealerID = contact.dealerID
			WHERE $where $closing; ";

	return $sql;
}

	# SQL to search on a user, given just one term
function SQL_userListOneTerm( $searchTerm) 
{
	$value = sql_escape('%'.$searchTerm.'%');

	if( is_numeric( $searchTerm)) {
		$where = "synergy_id = '$searchTerm'";
	}
	else {
		if(preg_match('/([[:alpha:]\']+) ([[:alpha:]\']+)/', $searchTerm, $name)) {
			$where = "firstname like ".sql_escape('%'.$name[1].'%')." and lastname like ".sql_escape('%'.$name[2].'%');
		}
		else {
			$where = "firstname like $value or lastname like $value or username like $value";
		}
	}

	$sql= " select	
			user.synergy_id as synergyID,
			user.id as portalID,
			user.firstname,
			user.lastname as surname,
			groups.name as groupname
		from	user
		left join groups on user.group_id = groups.id 
		WHERE $where
		order by surname, firstname
	";
	return $sql;
}


function SQL_userDetails( $portalID) {
	$portalID = sql_escape( $portalID);

	$sql= " select	
			user.id as portalID,
			synergy_id as synergyID,
			username,
			firstname,
			lastname as surname,
			groups.name as group_name,
			luCampus.name as campus,
			year,
			house.house,
			local_email,
			alt_email
		from user 
		left join userCampus on user.id = userCampus.uid
		left join luCampus on userCampus.campus = luCampus.id
		left join groups on user.group_id = groups.id
		left join house on user.house = house.id
		where user.id = $portalID
	";
	return $sql;
}


function SQL_userDetailsPortalLogin( $username) {
	$username = sql_escape( $username);
	$sql= "	select	*
		from	".DB_NAME_PORTAL.".user
		where	username = $username
	";
	return $sql;
}


function SQL_userDetailsPortal( $portalID) {
	$sql= "	select	*
		from	".DB_NAME_PORTAL.".user
		where	id = '$portalID'
	";
	return $sql;
}


function SQL_jobDetails( $jobID) {
	$jobID = sql_escape( $jobID);
	if( $jobID == '') {
		die( "Oh no, SQL_jobDetails for no jobID.<br>Chenque, yagshamash.");
	}
	$sql= " select	job.jobID,
			DATE_FORMAT(job.createdDate, '%a, %D %b %Y') as createdDate,
			DATE_FORMAT(job.createdDate, '%k:%i') as createdTime,
			DATE_FORMAT(job.dueDate, '%a, %D %b %Y') as dueDate,
			job.priorityID,
			job.campusID,
			job.latestEvent,
			campus.description as campus,
			prio.description as priority,
			prio.colour as priorityColour,
			job.equipmentID,
			e.serial as equipmentSerial,
			concat( b.description, ' ', m.description) as equipmentModel,
			concat( equipOwner.firstname, ' ', equipOwner.lastname) as equipmentOwnerName,
			equipOwner.username as equipmentOwnerUsername,
			equipOwner.id equipmentOwnerPortalID,
			e.userNonSynergy as equipmentUserNonSynergy,
			e.userID as equipmentUserSynergyID,
			job.assignedUserID,
			concat(assignedUser.firstname, ' ', assignedUser.lastname) as assignedUser,
			assignedUser.username as assignedUsername,
			job.portalAssignedUserID,
			concat(createdBy.firstname, ' ', createdBy.lastname) as createdUser,
			createdBy.username as createdUsername,
			job.portalCreatedUserID,
			job.description,
			job.problemID,
			prob.description as problem,
			job.statusID,
			s.description as status,
			concat( associatedUser.firstname, ' ', associatedUser.lastname) as associatedUserName,
			associatedUser.username as associatedUserUsername,
			associatedUser.synergy_id as associatedUserSynergyID,
			associatedUser.id portalAssociatedUserID
		from	tblJob job
			left join
			tblCampus campus on job.campusID = campus.campusID
			left join
			".DB_NAME_PORTAL.".user createdBy on job.portalCreatedUserID = createdBy.id
			left join
			tblPriority prio on prio.priorityID = job.priorityID
			left join
			tblEquipment e on e.equipmentID = job.equipmentID
			left join
			".DB_NAME_PORTAL.".user equipOwner on equipOwner.id = e.portalUserID
			left join
			tblModel m on m.modelID = e.modelID
			left join
			tblBrand b on b.brandID = m.brandID
			left join
	 		tblProblem prob on prob.problemID = job.problemID
			left join
			tblStatus s on s.statusID = job.statusID
			left join
			".DB_NAME_PORTAL.".user assignedUser on job.portalAssignedUserID = assignedUser.id
			left join
			".DB_NAME_PORTAL.".user associatedUser on job.portalAssociatedUserID = associatedUser.id
		where	jobID = " . $jobID;
	return $sql;
}


function SQL_equipmentDetails( $equipmentID) {
	$sql = "
		select	e.serial,
			e.active,
			e.equipmentTypeID,
			e.warrantyYears,
			e.insurance,
			equipOwner.synergy_id as userID,
			e.portalUserID,
			concat( equipOwner.firstname, ' ', equipOwner.lastname) as equipmentOwnerName,
			e.userNonSynergy,
			t.description as equipmentType,
			e.dateOfPurchase,
			m.brandID as brandID,
			b.description as brand,
			e.modelID,
			m.description as model,
			e.dealerID,
			d.description as dealer,
			e.locationID,
			l.description as location,
			l.campusID as campusID,
			c.description as campus,
			e.ownerID,
			o.description as owner
		from	tblEquipment e
			left join
			tblModel m
			on m.modelID = e.modelID
			left join
			tblBrand b
			on b.brandID = m.brandID
			left join
			tblDealer d
			on d.dealerID = e.dealerID
			left join
			tblLocation l
			on l.locationID = e.locationID
			left join
			tblCampus c
			on c.campusID = l.campusID
			left join
			tblEquipmentType t
			on t.equipmentTypeID = e.equipmentTypeID
			left join
			tblOwner o
			on o.ownerID = e.ownerID
			left join
		 	".DB_NAME_PORTAL.".user equipOwner on equipOwner.id = e.portalUserID
		where	e.equipmentID = " . sql_escape( $equipmentID);

	return $sql;
}

	# Get list of specs for a specified equipmentID
	# tblEquipmentSpec relates equipmentID (e.g. notebook 23) to spec (OS) and specOption (Win) with an optional freetext value (Minix)
	# If both specOption and value are set, it's up to the app which it uses.
	# Should prolly use the specOption (lookup) value.
function SQL_equipmentSpecs( $equipmentID) {
	$equipmentID = sql_escape( $equipmentID);
	$sql= ' select	s.specID,
			s.description as specDescription,
			o.specOptionID,
			o.description as specOptionValue,
			e.value as specValue
		from	tblEquipmentSpec e
			left join
			tblSpecOption o on e.specOptionID = o.specOptionID
			left join
			tblSpec s on s.specID = e.specID
		where	e.equipmentID = ' . $equipmentID;
	return $sql;
}


	# Auth via portal database
function SQL_auth( $username, $passwd) {
	$username = sql_escape( $username);
	$passwd = sql_escape( $passwd);
	$sql = "
		select	u.firstname,
			u.lastname,
			s.authlevel,
			u.id as portalid,
			u.synergy_id as synergyid,
			c.name as campus
		from	".DB_NAME.".tblStaff s
			inner join
			".DB_NAME_PORTAL.".user u on s.portalID = u.id
			left join
			".DB_NAME_PORTAL.".userCampus uc on uc.uid = u.id 
			left join 
			".DB_NAME_PORTAL.".luCampus c on uc.campus = c.id
               	where	u.username = $username
                	and
                	u.email_pass = $passwd
	";
	return $sql;
}


	# List id, description of supplied lookup table
function SQL_LookupList( $name) {
	$name = $name;
	if( $name == 'AssignedUser') {
		$sql = "
			select	u.firstname as description,
				u.id as id
			from	tblStaff s
				join
				".DB_NAME_PORTAL.".user u on s.portalID = u.id
			order by description
		";
	}
	else {
		$sql = '
			select	' . $name . 'id as id,
				description';

		if( $name == 'Model') {
			$sql .= ', brandID';
		}
		if( $name == 'Location') {
			$sql .= ', campusID';
		}
		if( $name == 'EventType') {
			$sql .= ', userVisible';
		}
		$sql .= '
			from	tbl' . $name . ' order by description';
	}
	
	if(( $name == 'Model') or ($name == 'Location') or ($name == 'Status')) {
		$sql .= ' desc';
	}
	return $sql;
}


	# List specOptionID and description for a given spec name
function SQL_LookupListSpec( $specName) {
	$specName = sql_escape( $specName);
	$sql = "
		select	specOptionID as id,
			o.description as description
		from	tblSpecOption o
			left join
			tblSpec s on o.specID = s.specID
		where	s.description = " . $specName
	;
	return $sql;
}

	# List locations primary
function SQL_LookupListLocationPrimary() {
	$sql = '
		select	locationID as id,
			description
		from	tblLocation
		where	campusID is null'
	;
	return $sql;
}

	# Update equipment item
function SQL_equipmentUpdate( &$parms) {
	$fields = '';
	$values = '';

	$sql=	'update	tblEquipment
		 set';

	foreach( $parms as $key => $value) {
		$value = sql_escape( $value);
		if( $key != 'equipmentID') {
			$sql .= ' ' . $key . ' = ' . $value . ', ';
		}
	}
	$sql = substr( $sql, 0, strlen( $sql) - 2);

	$sql.=' where equipmentID=' . sql_escape( $parms[ 'equipmentID']);

	return $sql;
}

	# Add an event
	# gimcrack \JIM-krak\, noun: A showy but useless or worthless object; a gewgaw.
function SQL_eventAdd( &$parms) {
	$fields = '';
	$values = '';

	$sql = "insert into	tblJobEvent ";

	foreach( array_keys( $parms) as $key) {
		$fields .= $key . ', ';
		$value = sql_escape( $parms[ $key]);

		$values .= $value . ", ";
	}
	$fields = substr( $fields, 0, strlen( $fields) - 2);
	$values = substr( $values, 0, strlen( $values) - 2);

	$sql .= '(' . $fields . ') values (' . $values . ')';

	return $sql;
}

#This query retrieves status that an event should leave the job in after being added

function SQL_eventStatus($id)
{
	$sql = "select status from tblEventType where eventTypeID = $id";
	return $sql;	
}


	# Add a job
	# THis ANd the fuNctIon for adding equipment are very similar and could
	# easily be combined into one generic 'add' function, but would require the
	# other scripts to know the database table names. This way there's less coupling.
	# I could define constants in this file for the table names, but gah.
	# Anyway that's all small stuff =)
	# pogonip   \PAH-guh-nip\   (noun) : a dense winter fog containing frozen particles
function SQL_jobAdd( &$parms) {
	$fields = '';
	$values = '';

	$sql = "insert into	tblJob ";

	foreach( array_keys( $parms) as $key) {
		$fields .= $key . ', ';
		$value = sql_escape( $parms[ $key]);

		$values .= $value . ", ";
	}
	$fields = substr( $fields, 0, strlen( $fields) - 2);
	$values = substr( $values, 0, strlen( $values) - 2);

	$sql .= '(' . $fields . ', createdDate) values (' . $values . ', NOW())';

	return $sql;
}


function SQL_jobEventsDelete( $jobID) {
	$sql =" delete
		from	tblJobEvent
		where	jobID = " . sql_escape( $jobID) . "
	";
	return $sql;
}

function SQL_jobDelete( $jobID) {
	$sql =" delete
		from	tblJob
		where	jobID = " . sql_escape( $jobID) . "
	";
	return $sql;
}

function SQL_jobUpdate( &$parms) {
	$sql= "	update	tblJob
		set ";

	foreach( array_keys( $parms) as $key) {
		if( $key != 'jobID' && $key != 'portalCreatedUserID') {
			$value = sql_escape( $parms[ $key]);
			$sql .= "$key = $value, ";
		}
	}
	$sql = substr( $sql, 0, strlen( $sql) - 2);
	$sql .= " where jobID = " . sql_escape( $parms[ 'jobID']);

	return $sql;
}


	# Add equipment item
function SQL_equipmentAdd( &$parms) {
	$fields = '';
	$values = '';

	$sql = "insert into	tblEquipment";

	foreach( array_keys( $parms) as $key) {
		$fields .= $key . ', ';
		$value = sql_escape( $parms[ $key]);

		if( !isset( $value) or ($value == '') ) {
			$values .= "null, ";
		}
		else {
			$values .= $value . ", ";
		}
	}
	$fields = substr( $fields, 0, strlen( $fields) - 2);
	$values = substr( $values, 0, strlen( $values) - 2);

	$sql .= '(' . $fields . ') values (' . $values . ')';

	return $sql;
}

function SQL_equipmentDelete( $equipID) {
	$sql =" delete
		from	tblEquipment
		where	equipmentID = " . sql_escape( $equipID) . "
	";
	return $sql;
}

function SQL_equipmentIDFromUser( $synergyID) {
	$sql= "	select	equipmentID
		from	tblEquipment
		where	userID = " . $synergyID
	;
	return $sql;
}


	# Given a substring of a campus' name, return its ID in the helpdesk database
function SQL_campusID( $campus) {
	$sql="	select	campusID
		from	tblCampus
		where	description like '%$campus%'
	";

	return $sql;
}

	# Get all rows from a specified table
function SQL_tableDump( $table) {
	$sql="	select	*
		from	$table
	";

	return $sql;
}


	# Add to a table
function SQL_tableAdd( &$arrParms, $tableName) {
	foreach( array_keys( $arrParms) as $key) {
		$keys .=  $key . ',';
		$values .= sql_escape( $arrParms[ $key]) . ',';
	}
	$keys = substr( $keys, 0, strlen( $keys)-1);
	$values = substr( $values, 0, strlen( $values)-1);

	$sql="	insert
		into	$tableName
			(" . $keys . ") values (" . $values . ")
	";

	return $sql;
	
}

	# Delete from a table
function SQL_tableDelete( $id, $tableName) {
	$sql="	delete
		from	$tableName
		where	" . tableID( $tableName) . " = " . $id
	;

	return $sql;
}


	# If we're going to delete an item from one table, check other tables containing it
	# Necessary because MySQL's a bit crap in that it doesn't support foreign keys
	# (plus a million other things)
function SQL_arrCheckForeignKeyExists( $id, $tableNameKey) {
	$keyName = tableID( $tableNameKey);

		# Array of tables that reference other tables
	$arrTableNames = array( 'tblModel', 'tblEquipment', 'tblJob', 'tblLocation', 'tblSpecOption');

	$sql = '';
	$arrCheck = array();
	foreach( $arrTableNames as $tableName) {
		if( $tableName != $tableNameKey) {
			$sql="select	*
				from	$tableName
				limit 0,1";
			$arrTmp=array( 	'sql'		=> $sql,
					'tableName'	=> $tableName
			);
			array_push( $arrCheck, $arrTmp);
		}
	}
	return( $arrCheck);
}

	# If we're going to delete an item from one table, check other tables containing it
	# Necessary because MySQL's a bit crap in that it doesn't support foreign keys
	# (plus a million other things)
function SQL_checkForeignKey( $idValue, $tableName, $idName) {
	#$Name = tableID( $tableName);

	$sql="	select	$idName
		from	$tableName
		where	$idName = '$idValue'
	";

	return( $sql);
}



	########################################
	# Utility functions
	########################################



	# Return the table qualifier for a field, if required
function tableQualifier( $key) {
	$arrIDs = array(
		'brandID'		=> 'm.',
		'modelID'		=> 'm.',
		'equipmentTypeID'	=> 'e.',
		'equipmentID'		=> 'e.',
		'statusID'		=> 'j.',
		'problemID'		=> 'p.',
		'locationID'		=> 'l.'
	);

	return $arrIDs[ $key];
}

	# Given a table name, construct the PK column name for it
function tableID( $tableName) {
	$columnName = strtolower( substr( $tableName, 3, 1)) . substr( $tableName, 4, strlen( $tableName)) . 'ID';
	return $columnName;
}


	# Given a generic fieldname (e.g. preferred) Return the name
	# of the column in the student table (e.g. StudentPrefferred)
function fieldnameStudent( $fieldname) {
	$arrTmp = array(	'preferred'			=> 'StudentPreferred',
				'surname'			=> 'StudentSurname',
				'CurrentSemesterOnlyFlag'	=> 'CurrentSemesterOnlyFlag',
				'synergyID'			=> 'StudentID'
	);
	$fieldnameStudent = $arrTmp[ $fieldname];
	if( $fieldnameStudent == '') {
		die( "fieldnameStudent : didn't know field name in vStudentsAll for '" . $fieldname . "'. Exiting.");
	}
	return $fieldnameStudent;
}


	# Generic function to construct a 'where' clause from an associative array
	# Always does 'likes' which will be slower but it's saving developer time
function whereClause( $parms) {
	$where = NULL;
	if( is_array( $parms)) {
		$where .= ' where ';
		foreach( array_keys( $parms) as $key) {
			$value = $parms[ $key];
			if( $value <> '') {									# If the parm is set
				$parmFlag = 1;
				$where .= tableQualifier( $key) . $key . ' like ' . sql_escape( '%' . $value . '%') . ' AND ';
			}
		}
		if( $parmFlag == 1) {
			$where = substr( $where, 0, strlen( $where) - 5); 					# trim off trailing ' AND '
		}
		else {												# trim of trailing ' where ';
			$where = substr( $where, 0, strlen( $where) - 6);
		}
	}
	return $where;
}


	# Given a generic fieldname (e.g. preferred) Return the name
	# of the column in the staff table (e.g. StaffPrefferredName)
function fieldnameStaff( $fieldname) {
	$arrTmp = array(	'preferred'	=> 'StaffPreferredName',
				'surname'	=> 'StaffSurname',
				'synergyID'	=> 'StaffID'
	);
	$fieldnameStaff = $arrTmp[ $fieldname];
	if( $fieldnameStaff == '') {
		die( "fieldnameStaff : didn't know field name in vStaff for '" . $fieldname . "'. Exiting.");
	}
	return $fieldnameStaff;
}

        # Escape a sql parameter for passing to mysql
function sql_escape( $var) {
        if( $var == '') {
                $var = 'null';
        }
        else {
           // Stripslashes
           if (get_magic_quotes_gpc()) {
               $var = stripslashes($var);
           }
           // Quote if not integer
           if (!is_numeric($value)) {
               $var = "'" . mysql_escape_string($var) . "'";
           }

        }
        return $var;
}


?>
