<?php

require_once( 'include/helpDeskPage.php');

	$tpl = new Module(TPL_DIR);
	$tpl->set_file('tplContent', 'dealerDetails.html');

	$page = new HelpDeskPage;
	$page->authCheck(1);
	
	if(isset($_GET['dealerID'])&& $_GET['dealerID'])
	{
		$title = "Provider Details";
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
			$tpl->set_block('tplContent','dealerContactBlock', 'clb');
			
			$i = 0;
			mysql_connect(DB_HOST, DB_USER, DB_PASSWD);
			mysql_select_db(DB_NAME);
			$sql = "SELECT * FROM tblDealerContact WHERE dealerID = '" . $dealerID ."'";
			$queryContact = mysql_query($sql);

		

			if(mysql_num_rows($queryContact) == 0)
			{	
						$tpl->set_var('clb', '');
						$tpl->set_var('c1', '');
			}
			else
			{

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
			$tpl->parse( 'clb', 'dealerContactBlock');
			}
			
			
		$content = $tpl->parse('Output', 'tplContent');		
		$page->setContent($content);
		$page->setTitle("Provider Details - ". $result['description']);
		$page->printPage();
	
		}
	}
		


?>