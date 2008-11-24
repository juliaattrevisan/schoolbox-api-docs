<?php

//staff object...allows you to add/modify/delete staff from staff table


class staff
{
	function staff($id = "")
	{
		
		//setup database
		$SQL_db = "sports";
		$this->mySQLdb = new dbManager($SQL_db);
		$this->mySQLdb->dbConnect();

		$this->campus = 1;

		$this->id = $id;
		if ($this->id){
			$this->exists = $this->staffExists();
		}
	}
	
	function getStaff()
	{
		$query = "SELECT staffid, lastname as lname, firstname as fname, phone, email
			  FROM staff
			  ORDER BY lname, fname
		";
	 	$this->mySQLdb->dbDoQuery($query);	   	
		while ($staff = $this->mySQLdb->dbGetNextArray())
		{
			$staff['lname'] = stripslashes($staff['lname']);
			$staff['fname'] = stripslashes($staff['fname']);
			$staff['phone'] = stripslashes($staff['phone']);
			$staff['email'] = stripslashes($staff['email']);
			$listOfStaff[] = $staff;
		}
		return $listOfStaff;
	}

	function addStaff($lastname, $firstname, $phone, $email)
	{
		$lastname = addslashes($lastname);
		$firstname = addslashes($firstname);
		$phone = addslashes($phone);
		$email = addslashes($email);
		if(!$this->exists)
		{
			$query = "SELECT * FROM staff 
				  WHERE lastname = '".$lastname."' 
				  AND firstname = '".$firstname."'
				";
			$this->mySQLdb->dbDoQuery($query);
			if ($this->mySQLdb->dbCountRows() == 0)
			{
				$query = "INSERT INTO staff (lastname, firstname, phone, email, campus) 
					  VALUES ('".$lastname."','".$firstname."','"
					  .$phone."','".$email."', '".$this->campus."')
				";
				if($this->mySQLdb->dbDoQuery($query))
					return 1;
				else
					error(21);
			}
			else
				error(38);
		}
		else
			error(38);
		
	}
	
	function modifyStaff($id, $lname, $fname, $phone, $email)
  	{
		$lname = addslashes($lname);
		$fname = addslashes($fname);
		$phone = addslashes($phone);
		$email = addslashes($email);
		if($this->StaffExists($id))
		{
			$query = "UPDATE staff SET lastname = '".$lname."', 
				  		   firstname = '".$fname."',
						   phone = '".$phone."',
						   email = '".$email."'
				  WHERE staffid = '".$id."'
			";
			if($this->mySQLdb->dbDoQuery($query))
				return 1;
			else
				error(0);
		}
		else 
			print "staff member doesn't exist";
	}	
	
	function deleteStaff()
	{
		if($this->exists)
		{
			$query = "DELETE FROM staffteam WHERE staffid = '".$this->id."'";
			$this->mySQLdb->dbDoQuery($query);

			$query = "DELETE FROM staff 
				  WHERE staffid = '".$this->id."'
			"; 
			$this->mySQLdb->dbDoQuery($query);
		}
		else print "whoops, not existing...";

	}

	function staffExists($id = "")
	{
		if ($id)
		{
			$this->id = $id;
		}
		$query = "SELECT * FROM staff 
			  WHERE  staffid = '".$this->id."'
		";
		$this->mySQLdb->dbDoQuery($query);
		if ($this->mySQLdb->dbCountRows() > 0)
			return 1;
		else
			return 0;
	}
}

?>
