<?php

$this->setBlock('REPORT_CONTENT', 'month_head', 'month_head_block' );
$this->setBlock('REPORT_CONTENT', 'created_user_role', 'created_user_role_block' );
$this->setBlock('created_user_role', 'created_user_role_month', 'created_user_role_month_block' );
$this->setBlock('REPORT_CONTENT', 'viewed_user_role', 'viewed_user_role_block' );
$this->setBlock('viewed_user_role', 'viewed_user_role_month', 'viewed_user_role_month_block' );


$months = $start->diffInMonths($end);

/** @var Alaress_Date_Format $currentMonth   */
$currentMonth = clone $start;

$report = new Schoolbox\Engagement\CMS();

$roleMapper = new \Schoolbox\RoleMapper();
$roleList = $roleMapper->findAll();

foreach($roleList as $role) {
    $createdTable[$role->getName()] = array_fill(0,$months, 0);
    $viewedTable[$role->getName()] = array_fill(0,$months, 0);

}

for($i=0; $i < $months; $i++) {

    $this->setVar('MONTH', $currentMonth->getUsingFormat('F Y'));
    $this->parse('month_head_block', 'month_head', true);


    $endMonth = clone $currentMonth;
    $endMonth->endOfMonth();

    $created = $report->log($currentMonth, $endMonth, 'create', 'file');
    foreach($created as $row) {
        $createdTable[$row['name']][$i] = $row['total'];
    }

    $viewed = $report->log($currentMonth, $endMonth, 'access', 'file');
    foreach($viewed as $row) {
        $viewedTable[$row['name']][$i] = $row['total'];
    }

    $currentMonth->addMonths(1);
}

foreach($createdTable as $role => $monthTotal) {

    $this->setVar('USER_ROLE',$role);

    foreach($monthTotal as $total) {
        $this->setVar('CREATED_MONTH_COUNT', $total);
        $this->parse('created_user_role_month_block','created_user_role_month',true);
    }

    $this->parse('created_user_role_block', 'created_user_role', true);
    $this->setVar('created_user_role_month_block','');

}

foreach($viewedTable as $role => $monthTotal) {

    $this->setVar('USER_ROLE',$role);

    foreach($monthTotal as $total) {
        $this->setVar('VIEWED_MONTH_COUNT', $total);
        $this->parse('viewed_user_role_month_block','viewed_user_role_month',true);
    }

    $this->parse('viewed_user_role_block', 'viewed_user_role', true);
    $this->setVar('viewed_user_role_month_block','');

}