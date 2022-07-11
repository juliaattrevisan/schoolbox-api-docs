<?php

/**
 * If you authenticated via saml, you don't have the password set in
 * the session, so ask people to enter it again from here before
 * redirecting back to webdav
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/** @var SitePage $this */

if (strlen($_POST['password'] ?? '')) {
    $_SESSION['password'] = $_POST['password'];

    // Redirect
    // * no lite, no iframe: /drives
    // * no lite, iframe:    /drives/picker/webdav
    // * lite, no iframe:    /ajax/resource/tree/webdav
    // * lite, iframe:       /ajax/resource/tree/webdav
    $redir = '/drives';
    if (isset($this->arg[0]) && $this->arg[0] === 'lite') {
        $redir = '/ajax/resource/tree/webdav';
    } elseif ((int)$_POST['iframe'] === 1) {
        $redir = '/drives/picker/webdav';
    }

    $this->localRedir($redir);
} else {
    $this->setFile(TEMPLATE_content, 'cms/networkLogin.html');
    $this->setBlock(TEMPLATE_content, 'lite', 'lite_block');

    $this->setVar(
        [
            'NETWORK_DRIVES_USERNAME' => $_SESSION['username'],
            'SELF'                    => '/cms/networkLogin/' . implode('/', $this->arg),
        ]
    );

    if (isset($this->arg[0]) && $this->arg[0] == 'lite') {
        $this->parse('lite_block', 'lite');
        $this->setAjax();
        $this->injectGlobalVars();
        $this->template->pparse('finaloutput', TEMPLATE_content);
    } else {
        $this->setVar('lite_block', '');
    }
}
