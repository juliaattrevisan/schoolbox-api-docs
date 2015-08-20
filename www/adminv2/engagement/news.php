<?php
/**
 * Display due work
 */

$this->setBlock('REPORT_CONTENT', 'month_head', 'month_head_block' );
$this->setBlock('REPORT_CONTENT', 'school_month_body', 'school_month_body_block' );
$this->setBlock('REPORT_CONTENT', 'group_month_body', 'group_month_body_block' );

$months = $start->diffInMonths($end);

/** @var Alaress_Date_Format $currentMonth   */
$currentMonth = clone $start;

$report = new Schoolbox\Engagement\News();

for($i=0; $i < $months; $i++) {

    $this->setVar('MONTH', $currentMonth->getUsingFormat('F Y'));
    $this->parse('month_head_block', 'month_head', true);


    $endMonth = clone $currentMonth;
    $endMonth->endOfMonth();


    $school = $report->schoolCreated($currentMonth, $endMonth);
    $group = $report->groupCreated($currentMonth, $endMonth);

    $this->setVar('SCHOOL_MONTH_COUNT', $school);
    $this->parse('school_month_body_block', 'school_month_body', true);

    $this->setVar('GROUP_MONTH_COUNT',$group);
    $this->parse('group_month_body_block', 'group_month_body', true);



    $currentMonth->addMonths(1);
}