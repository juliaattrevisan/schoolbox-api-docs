<?php

$this->setBlock('REPORT_CONTENT', 'month_head', 'month_head_block' );
$this->setBlock('REPORT_CONTENT', 'mobile_user', 'mobile_user_block' );
$this->setBlock('mobile_user', 'mobile_user_month', 'mobile_user_month_block' );


$months = $start->diffInMonths($end);

/** @var Alaress_Date_Format $currentMonth   */
$currentMonth = clone $start;

$report = new Schoolbox\Engagement\Mobile();

$roleMapper = new \Schoolbox\RoleMapper();
$roleList = $roleMapper->findAll();

foreach($roleList as $role) {
    $mobileTable[$role->getName()] = array_fill(0,$months, 0);

}

for($i=0; $i < $months; $i++) {

    $this->setVar('MONTH', $currentMonth->getUsingFormat('F Y'));
    $this->parse('month_head_block', 'month_head', true);


    $endMonth = clone $currentMonth;
    $endMonth->endOfMonth();

    $created = $report->registered($currentMonth, $endMonth);
    foreach($created as $row) {
        $mobileTable[$row['name']][$i] = $row['total'];
    }

    $currentMonth->addMonths(1);
}

foreach($mobileTable as $role => $monthTotal) {

    $this->setVar('USER_ROLE',$role);

    foreach($monthTotal as $total) {
        $this->setVar('MOBILE_COUNT', $total);
        $this->parse('mobile_user_month_block','mobile_user_month',true);
    }

    $this->parse('mobile_user_block', 'mobile_user', true);
    $this->setVar('mobile_user_month_block','');

}