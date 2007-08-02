<?php

require_once( 'include/helpDeskPage.php');

	$tpl = new Module(TPL_DIR);
	$tpl->set_file('tplContent', 'dealerEdit.html');

	$page = new HelpDeskPage;
	$page->authCheck(2);

if (!(isset($_POST['Submit']) && $_POST['Submit'] == 'Submit'))
{


	
	if(isset($_GET['dealerID'])&& $_GET['dealerID'])
	{
		$title = "Edit Provider";
		$dealerID = $_GET['dealerID'];

		mysql_connect(DB_HOST, DB_USER, DB_PASSWD);
		mysql_select_db(DB_NAME);
		$sql = "SELECT * FROM tblDealer WHERE dealerID = '" . $dealerID ."'";
		$query = mysql_query($sql);
		//	or die( 'Error querying database for get Dealer Details : ' . mysql_error());
		
		if(mysql_num_rows($query) == 0)
		{
			echo("<script>history.go(-1);</script>"); //add error code
		}
		else
		{
			$result = mysql_fetch_array($query);
			//$tpl->set_var('DEALERNAME', $result['contactname']);
			//$tpl->set_var('DEALERPOS', $result['contactposition']);
			//$tpl->set_var('DEALEREMAIL', $result['email']);
			$tpl->set_var('DEALERTYPE', $result['type']);
			$tpl->set_var('DEALERADDR', $result['address']);
			//$tpl->set_var('DEALERPHONE', $result['telephone']);
			$tpl->set_var('DEALERFAX', $result['fax']);
			$tpl->set_var('DEALERDESC', $result['description']);
			$tpl->set_var('UPDATEYN', '1');
			$tpl->set_var('DEALERID', $result['dealerID']);
			$tpl->set_var('DELETEBUTTON', "<input type='checkbox' name='delete' value='1'>");
			$tpl->set_var('DELETELABEL', "<label for='delete'>Delete Dealer:</label>");	
			
			$tpl->set_block('tplContent','contactList', 'cl');
			
			$i = 0;
			$sql = "SELECT * FROM tblDealerContact WHERE dealerID = '" . $dealerID ."'";
			$queryContact = mysql_query($sql);

			while($row = mysql_fetch_array($queryContact)) 
			{
				$tpl->parse( 'cl', 'contactList', true);
				$tpl->set_var('CONTACTNAME', $row['name']);
				$tpl->set_var('CONTACTPOS', $row['pos']);
				$tpl->set_var('CONTACTEMAIL', $row['email']);		
				$tpl->set_var('CONTACTPHONE', $row['phoneno']);
				$tpl->set_var('CONTACTID', $row['contactID']);
				$tpl->set_var('LOOP', $i);
				$tpl->set_var('DELETECONTACTBUTTON', "<input name='deleteContact".$i."' type='checkbox' value='". $row['contactID'] ."'>");
			
				$i++;
			}
	
			$tpl->parse( 'cl', 'contactList',true);
			$tpl->set_var('CONTACTNAME', "");
			$tpl->set_var('CONTACTPOS', "");
			$tpl->set_var('CONTACTEMAIL', "");		
			$tpl->set_var('CONTACTPHONE', "");
			$tpl->set_var('CONTACTID', "new");
			$tpl->set_var('LOOP', "new");
			$tpl->set_var('DELETECONTACTBUTTON', "");



			$tpl->set_var('TOTALCONTACTS', $i+1);
			$content = $tpl->parse('Output', 'tplContent');
			$page->setContent($content);
			$page->setTitle("Edit Provider - ". $result['description']);
			$page->printPage();
	
		}
	}
		//new dealer
	else
	{
	//dealer stuff
		//$tpl->set_var('DEALERNAME', "");
		//$tpl->set_var('DEALERPOS', "");
		//$tpl->set_var('DEALEREMAIL', "");
		$tpl->set_var('DEALERTYPE', "");
		$tpl->set_var('DEALERADDR', "");
		//$tpl->set_var('DEALERPHONE', "");
		$tpl->set_var('DEALERFAX', "");
		$tpl->set_var('DEALERDESC', "");
		$tpl->set_var('UPDATEYN', '0');
		$tpl->set_var('DEALERID', "");
		$tpl->set_var('DELETEBUTTON', "");
		$tpl->set_var('DELETELABEL', "");	
		$tpl->set_var('TOTALCONTACTS', "1");	
		$tpl->set_block('tplContent','contactList', 'cl');
	//contact stuff		
		$tpl->set_var('CONTACTNAME', "");
		$tpl->set_var('CONTACTPOS', "");
		$tpl->set_var('CONTACTEMAIL', "");		
		$tpl->set_var('CONTACTPHONE', "");
		$tpl->set_var('CONTACTID', "new");
		$tpl->set_var('LOOP', "new");
		$tpl->set_var('DELETECONTACTBUTTON', "");
		$tpl->parse( 'cl', 'contactList');

		$content = $tpl->parse('Output', 'tplContent');
		$page->setContent($content);
		$page->setTitle("Add Dealer");
		$page->printPage();
		
	}
	
}

if(isset($_POST['Submit']) && $_POST['Submit'] == 'Submit')
{
	mysql_connect(DB_HOST, DB_USER, DB_PASSWD);
	mysql_select_db(DB_NAME);
	
	//dealer	
	if($_POST['update'] == 1)
	{
	
		if($_POST['delete'] == 1)
		{
			mysql_query("DELETE FROM tblDealer WHERE dealerID = '".$_POST['dealerID']."'");						
			mysql_query("DELETE FROM tblDealerContact WHERE dealerID = '".$_POST['dealerID']."'");						

		}
		
		$sql = "UPDATE tblDealer SET
				description = '" . $_POST['dealerDesc'] . "',
				type = '" . $_POST['dealerType'] . "',
				address = '" . $_POST['dealerAddr'] . "',
				fax = '" . $_POST['dealerFax'] . "',
				WHERE dealerID = '". $_POST['dealerID']."'";
				
		mysql_query($sql);
		$dealerid = $_POST['dealerID'];				

	}
	else
	{
		$sql = "INSERT INTO tblDealer(description, type, address, fax)
				VALUES ('" . $_POST['dealerDesc'] . "', '" . $_POST['dealerType'] . "',  
				 '" . $_POST['dealerAddr'] . "', '" . $_POST['dealerFax'] . "')";
				
		mysql_query($sql);
		$dealerid = mysql_insert_id();		
	}	
	
	
	if ($_POST['contactIDnew'] == "new" && $_POST['contactNamenew'] != "")
		{
		$sql="INSERT INTO tblDealerContact(name, pos, phoneno, email, dealerID) VALUES('".
			$_POST['contactNamenew']."','".
			$_POST['contactPosnew']."','".
			$_POST['contactPhonenew']."','".
			$_POST['contactEmailnew']."','".
			$dealerid."')";
			
		mysql_query($sql);
		}	


	for($i=0;$i<$_POST['totalcontacts'];$i++)
		{
		if ($_POST['contactID'.$i] == $_POST['deleteContact'.$i])
			{
			$sql="DELETE FROM tblDealerContact where contactID = '" . $_POST['contactID'.$i]."'";
			mysql_query($sql);			
			}
		else 
			{
			$sql="UPDATE tblDealerContact SET 
				name = '" .$_POST['contactName'.$i]. "',
				pos = '" .$_POST['contactPos'.$i]. "',
				phoneno = '" .$_POST['contactPhone'.$i]. "',
				email = '" .$_POST['contactEmail'.$i]. "' WHERE contactID = ". $_POST['contactID'.$i] ;
			mysql_query($sql);		
					
			}
		}
	
redirectTo(	'/dealerEdit.php?dealerID='.$dealerid );

}
	
	


?>