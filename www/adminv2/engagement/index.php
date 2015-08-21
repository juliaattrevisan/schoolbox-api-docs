<?php

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/engagement/index.html');
$this->setTitle('Admin - Engagement');


// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'engagement');

$validPages = array('dueWork','quiz','files', 'links','fileTypes','news','collaboration','login','components');

/* Setup period **/
$start = new Alaress_Date_Format();
$start = $start->addMonths(-6);
$start = $start->startOfMonth();

$end = new Alaress_Date_Format();
$end = $end->endOfMonth();


if(in_array($this->arg[0],$validPages)) {
    $page = $this->arg[0];
}
else {
    $page = 'login';
}


$this->setFile('REPORT_CONTENT', 'adminv2/engagement/' . $page . '.html');

include(dirname ( __FILE__ ) .'/'. $page.'.php');

$this->parse('REPORT_DATA', 'REPORT_CONTENT');
