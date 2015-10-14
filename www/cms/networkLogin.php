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

/** @var SitePage $this */

if (strlen($_POST['password'])) {
	$_SESSION['password'] = $_POST['password'];

	/* Redirect
	 */
    $redir = isset($this->arg[0]) && $this->arg[0] == 'lite'
        ? '/ajax/resource/tree/webdav'
        : '/drives';

	$this->localRedir($redir);
}
else {
	$this->setFile(TEMPLATE_content, 'cms/networkLogin.html');
    $this->setBlock(TEMPLATE_content, 'lite', 'lite_block');

    $this->setVar(array(
        'NETWORK_DRIVES_USERNAME' => $_SESSION['username'],
        'SELF'                    => '/cms/networkLogin/' . implode('/', $this->arg)
    ));

    if (isset($this->arg[0]) && $this->arg[0] == 'lite') {
        $this->parse('lite_block', 'lite');
        $this->setAjax();
        $this->template->pparse('finaloutput', TEMPLATE_content);

    } else {
        $this->setVar('lite_block', '');
    }

}
