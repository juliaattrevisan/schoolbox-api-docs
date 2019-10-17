<?php

if (!$_SESSION['user']->isSuperuser()) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/engagement/index.html');
$this->setTitle('Admin - Engagement');

// include the admin navigation
$nav = new AdminV2_Nav($this->getDi()->get('config'));
$nav->parse($this, 'engagement');

$validPages = array('dueWork','quiz','files', 'links','fileTypes','news','collaboration','login','components','mobile');

/* Setup period **/
if($this->arg[1]) {
    $start = \Alaress_Date_Format::newUrlDate($this->arg[1]);
}
else {
    $start = new Alaress_Date_Format();
    $start = $start->addMonths(-6);
}
$start = $start->startOfMonth();

if($this->arg[2]) {
    $end = \Alaress_Date_Format::newUrlDate($this->arg[2]);
}
else {
    $end = new Alaress_Date_Format();
}
$end = $end->endOfMonth();

if(in_array($this->arg[0],$validPages)) {
    $page = $this->arg[0];
}
else {
    $page = 'login';
}

$this->setVar(array(
    'CURRENT_PAGE'              => $page,
    'SELECTED_START'            => $start->getLocalShort(),
    'SELECTED_FINISH'           => $end->getLocalShort(),
    'SELECTED_FORMATTED_START'  => $start->getURLDate(),
    'SELECTED_FORMATTED_FINISH' => $end->getURLDate(),
));

foreach($validPages as $pageNav) {
    if($pageNav == $page) {
        $this->setVar('SETTING_TAB_'.$pageNav, 'active');
    }
    else {
        $this->setVar('SETTING_TAB_'.$pageNav, '');
    }
}

$this->setFile('REPORT_CONTENT', 'adminv2/engagement/' . $page . '.html');

include(dirname ( __FILE__ ) .'/'. $page.'.php');

$this->parse('REPORT_DATA', 'REPORT_CONTENT');
