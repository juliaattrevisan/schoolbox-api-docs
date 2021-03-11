<?php

use Schoolbox\Admin\Navigation\Subnav;


if (!$_SESSION['user']->isSuperuser()) {
    error(19, "", 401);
}

$this->setFile(TEMPLATE_content, 'adminv2/engagement/index.html');
$this->setTitle('Admin - Engagement');

$validPages = [
    Subnav::CHILD_ENGAGEMENT_DUE_WORK,
    Subnav::CHILD_ENGAGEMENT_QUIZZES,
    Subnav::CHILD_ENGAGEMENT_FILES,
    Subnav::CHILD_ENGAGEMENT_LINKS,
    Subnav::CHILD_ENGAGEMENT_FILETYPES,
    Subnav::CHILD_ENGAGEMENT_NEWS_POSTED,
    Subnav::CHILD_ENGAGEMENT_COLLABORATION,
    Subnav::CHILD_ENGAGEMENT_LOGIN,
    Subnav::CHILD_ENGAGEMENT_COMPONENT_USAGE,
    Subnav::CHILD_ENGAGEMENT_MOBILE_APPS,
];

/* Setup period **/
if($this->arg[1]) {
    $start = \Alaress_Date_Format::newUrlDate($this->arg[1]);
}
else {
    $start = new Alaress_Date_Format();
    $start = $start->addMonths(-4);
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
    $page = 'collaboration';
}

// include the admin navigation
(new Subnav($this->getDi()->get('config')))
    ->generatePhplib($this, Subnav::PARENT_ENGAGEMENT, $page)
    ->generateBreadcrumbsHtml($this, Subnav::PARENT_ENGAGEMENT, $page);

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
