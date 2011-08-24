<?php

require_once($_SERVER['DOCUMENT_ROOT']."/include/session.php");
require_once($_SERVER['DOCUMENT_ROOT']."/include/include.php");
require_once('./createlm.php');

class Password {

function Password() {
	$this->db = new dbManager();
}

function displayForm() {
	
	$this->t_body = new Template(TPL_directory);
	
	$this->t_body->set_file("tplBODY", "modules/password/getPassword.htm");
	$this->t_body->set_var("TITLE", HEADING." - Change Password");
	
	include($_SERVER['DOCUMENT_ROOT'].'/include/page.php');

	$user = $db->dbGetSingleRow("SELECT username FROM user WHERE id = {$_SESSION['id']}");	
	
	$this->t_body->set_var('UID', $_SESSION['uid']);
	$this->t_body->set_var('USERNAME', $user['username']);

	$this->t_body->pparse('finalout','tplBODY');
}

function displayConfirmation($message) {
	$this->t_body = new Template(TPL_directory);
	
	$this->t_body->set_file("tplBODY", "modules/password/confirm.htm");
	$this->t_body->set_var("TITLE", HEADING." - Change Password Confirmation");
	
	include($_SERVER['DOCUMENT_ROOT'].'/include/page.php');

	$this->t_body->set_var('MESSAGE', $message);

	$this->t_body->pparse('finalout','tplBODY');
}
	
function changePassword($uname, $oldpassword1, $newpassword1) {

   $smb = new smbHash;

   $passwordarray["sambaLMPassword"] = $smb->lmhash($newpassword1);
   $passwordarray["sambaNTPassword"] = $smb->nthash($newpassword1);

   $dn = "uid=" . $uname . ",ou=Users,dc=ormond,dc=unimelb,dc=edu,dc=au";
   $ldapconn = ldap_connect("francis2.ormond.unimelb.edu.au", 389);
   $ldapbind = @ldap_bind($ldapconn,$dn,$oldpassword1);

   if($ldapbind) {
      if(ldap_modify ($ldapconn, $dn, $passwordarray)) { 
         $newpassword = escapeshellcmd($newpassword1);
         $oldpassword = escapeshellcmd($oldpassword1);
         $return = @system ("ldappasswd -h francis2.ormond.unimelb.edu.au -p 389 -x -D ".$dn." -w ".$oldpassword." -s ".$newpassword." > /dev/null");
         $this->displayConfirmation('Password Update Successful');
      } else { 
		$this->displayConfirmation('Password Update Failed');
      }
   } else {
		$this->displayConfirmation('Connection failed, please retry your password');
   }
}

}

$page = new Password;

if($_POST['uname'] && $_POST['password1']) {
	if($_POST['password1'] == $_POST['password2'])
		$page->changePassword($_POST['uname'], $_POST['current_password'], $_POST['password1']);
	else
		$page->displayConfirmation('Your passwords did not match please try again');
}
else
	$page->displayForm();


?>
