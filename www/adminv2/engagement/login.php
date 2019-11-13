<?php

use Schoolbox\Database;
use Schoolbox\IConfigLoader;
use Schoolbox\MapperFactory;
use Schoolbox\RoleMapper;
use Schoolbox\Engagement\Login;

/** @var SitePage $this */
$this->setBlock('REPORT_CONTENT', 'month_head', 'month_head_block' );
$this->setBlock('REPORT_CONTENT', 'login_total', 'login_total_block' );
$this->setBlock('login_total', 'login_total_month', 'login_total_month_block' );
$this->setBlock('REPORT_CONTENT', 'login_percentage', 'login_percentage_block' );
$this->setBlock('login_percentage', 'login_percentage_month', 'login_percentage_month_block' );

/**
 * @var Alaress_Date_Format $start
 * @var Alaress_Date_Format $end
 */
$months = $start->diffInMonths($end) + 1;

/** @var Alaress_Date_Format $currentMonth   */
$currentMonth = clone $start;

/**
 * @var MapperFactory $mapperFactory
 * @var IConfigLoader $config
 * @var RoleMapper    $roleMapper
 */
$mapperFactory = $this->getDi()->get('mapperFactory');
$config        = $this->getDi()->get('config');
$loginReport   = new Login(Database\Factory::getInstanceInternal(), $config->get('session_length'));
$roleMapper    = $mapperFactory->get(RoleMapper::class);
$roleList      = $roleMapper->findAll();


foreach($roleList as $role) {
    $totalTable[$role->getName()] = array_fill(0,$months, 0);
    $percentageTable[$role->getName()] = array_fill(0,$months, 0);

}

for($i=0; $i < $months; $i++) {

    $this->setVar('MONTH', $currentMonth->getUsingFormat('F Y'));
    $this->parse('month_head_block', 'month_head', true);


    $endMonth = clone $currentMonth;
    $endMonth->endOfMonth();

    $roleSummary   = $loginReport->userSummary($endMonth);

    $total = $loginReport->loginByGroup($currentMonth, $endMonth);
    foreach($total as $row) {
        $totalTable[$row['name']][$i] = $row['total'];
    }

    $unique = $loginReport->uniqueLoginByGroup($currentMonth, $endMonth);
    foreach($unique as $row) {
        foreach($roleSummary as $summary) {
            if($summary['name'] == $row['name']){
                $groupTotal = $summary['total'];
                break;
            }
        }

        $percentageTable[$row['name']][$i] = round(($row['total'])*100 / $groupTotal) ;
    }

    $currentMonth->addMonths(1);
}

foreach($totalTable as $role => $monthTotal) {

    $this->setVar('USER_ROLE',$role);

    foreach($monthTotal as $total) {
        $this->setVar('TOTAL_MONTH_COUNT', $total);
        $this->parse('login_total_month_block','login_total_month',true);
    }

    $this->parse('login_total_block', 'login_total', true);
    $this->setVar('login_total_month_block','');

}

foreach($percentageTable as $role => $monthTotal) {

    $this->setVar('USER_ROLE',$role);

    foreach($monthTotal as $total) {
        $this->setVar('PERCENTAGE_MONTH_COUNT', $total);
        $this->parse('login_percentage_month_block','login_percentage_month',true);
    }

    $this->parse('login_percentage_block', 'login_percentage', true);
    $this->setVar('login_percentage_month_block','');

}
