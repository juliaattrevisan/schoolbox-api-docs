<?php
require_once(dirname(__FILE__) . '/../../../include/config.php');
require_once(dirname(__FILE__) . '/../../../library/DeprecatedUser.php');
require_once(dirname(__FILE__) . '/../../../include/SitePage.php');
require_once('ormondLDAP.php');

if ($_POST['emailad'] && $_POST['password']) {
	$password = $_POST['password'];
	$uname = $_POST['uname'];
	$emailad = $_POST['emailad'];
	$message = '';
}
else if ($_POST['emailad']) {
	$message = 'You did not enter a password, please resubmit with your current Ormond password.';
}
else if ($_POST['password']) {
	$message = 'You did not enter a forwarding address, please resubmit.';
}
else {
	SitePage::localRedir('/modules/emailforward/modify');
}

/* Update
 */
if ($message == '') {
	$ldap = new OrmondLDAP();

	$dn = $ldap->getLDAPdn($uname);
	$ldapconn = ldap_connect("francis2.ormond.unimelb.edu.au", 389);
	$ldapbind = @ldap_bind($ldapconn, $dn, $password);

	$emailarray["mailRoutingAddress"] = $emailad;

	if ($ldapbind) {
		if (ldap_mod_replace($ldapconn, $dn, $emailarray)) {
			$message = 'Email Forward Successful';
		}
		else {
			$message = 'Email Update Failed';
		}
	}
	else {
		$message = 'Connection failed, please retry your password';
	}
}

/* Display response message
 */
$this->setTitle(HEADING . ' - Change Email Forward Confirmation');
$this->setFile(TEMPLATE_content, 'modules/emailforward/modifyForm.html');

$this->setVar(array(
	'MESSAGE' => $message,
));

