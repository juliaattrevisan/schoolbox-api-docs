<?php

// Check if we have already logged in to Synergetic

if ($_SESSION['communityLogin'] || !Config::get('synergetic_launcher')) {
    // Already logged in, just display the iFrame

    $this->setFile(TEMPLATE_content, 'synergetic.html');
    $this->setTitle('School Records');

    if (preg_match('/^\w+$/', $this->arg[0])) {
        $this->setVar('PAGE_REQUEST', $this->arg[0]);
        $this->setVar('SYNERGETIC_MENU', $this->arg[1]);
        $this->setVar('ID_VALUE', $_GET['id']);
        $this->setVar('SN_VALUE', $_GET['sn']);
    } else {
        $this->setVar('PAGE_REQUEST', '');
        $this->setVar('SYNERGETIC_MENU', '');
        $this->setVar('ID_VALUE', '');
        $this->setVar('SN_VALUE', '');
    }
} else {
    // Not logged in, go to the launcher page

    $this->setFile(TEMPLATE_content, 'synergetic_launcher.html');
    $this->setTitle('School Records');
}
