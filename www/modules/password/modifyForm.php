<?php
require_once(dirname(__FILE__) . '/../../../include/Config.php');
require_once(dirname(__FILE__) . '/../../../library/user.php');
require_once(dirname(__FILE__) . '/../../include/stdfuncs.php');

/* Check inputs
 */
if ($_POST['uname'] && $_POST['password1']) {
	if ($_POST['password1'] == $_POST['password2']) {

		/* Change password
		 */
		$smb = new smbHash();

		$passwordarray["sambaLMPassword"] = $smb->lmhash($newpassword1);
		$passwordarray["sambaNTPassword"] = $smb->nthash($newpassword1);

		$dn = "uid=" . $uname . ",ou=Users,dc=ormond,dc=unimelb,dc=edu,dc=au";
		$ldapconn = ldap_connect("francis2.ormond.unimelb.edu.au", 389);
		$ldapbind = @ldap_bind($ldapconn,$dn,$oldpassword1);

		if ($ldapbind) {
			if (ldap_modify ($ldapconn, $dn, $passwordarray)) {
				$newpassword = escapeshellcmd($newpassword1);
				$oldpassword = escapeshellcmd($oldpassword1);
				$return = @system ("ldappasswd -h francis2.ormond.unimelb.edu.au -p 389 -x -D ".$dn." -w ".$oldpassword." -s ".$newpassword." > /dev/null");

				$message = 'Password Update Successful';
			}
			else {
				$message = 'Password Update Failed';
			}
		}
		else {
			$message = 'Connection failed, please retry your password';
		}
	}
	else {
		$message = 'Your passwords did not match please try again';
	}
}
else {
	localRedir('modify');
}

/* Display response message
 */
$this->setTitle(HEADING . ' - Change Password Confirmation');
$this->setFile(TEMPLATE_content, '../ormond/modules/password/modifyForm.html');

$this->setVar(array(
	'MESSAGE' => $message,
));
