<?php

$this->setBlock('REPORT_CONTENT', 'month_head', 'month_head_block' );
$this->setBlock('REPORT_CONTENT', 'created_month_body', 'created_month_body_block' );
$this->setBlock('REPORT_CONTENT', 'attempted_month_body', 'attempted_month_body_block' );


$months = $start->diffInMonths($end);

/** @var Alaress_Date_Format $currentMonth   */
$currentMonth = clone $start;

$report = new Schoolbox\Engagement\Evaluate();

for($i=0; $i < $months; $i++) {

    $this->setVar('MONTH', $currentMonth->getUsingFormat('F Y'));
    $this->parse('month_head_block', 'month_head', true);


    $endMonth = clone $currentMonth;
    $endMonth->endOfMonth();


    $created = $report->created($currentMonth, $endMonth,'assess');
    $attempted = $report->attempted($currentMonth, $endMonth, 'assess');

    $this->setVar('CREATED_MONTH_COUNT', $created);
    $this->parse('created_month_body_block', 'created_month_body', true);

    $this->setVar('ATTEMPTED_MONTH_COUNT',$attempted);
    $this->parse('attempted_month_body_block', 'attempted_month_body', true);

    $currentMonth->addMonths(1);
}