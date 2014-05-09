<?php
/**
 * If you authenticated via saml, you don't have the password set in
 * the session, so ask people to enter it again from here before
 * redirecting back to webdav
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (strlen($_POST['password'])) {
	$_SESSION['password'] = $_POST['password'];

	/* Redirect
	 */
	$this->localRedir('/cms/network');
}
else {
	$this->setFile(TEMPLATE_content, 'cms/networkLogin.html');

	$this->setVar(array(
		'NETWORK_DRIVES_USERNAME' => $_SESSION['username']
	));
}
