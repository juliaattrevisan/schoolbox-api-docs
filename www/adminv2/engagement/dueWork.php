<?php
/**
 * Display due work
 */

$this->setBlock('REPORT_CONTENT', 'month_head', 'month_head_block' );
$this->setBlock('REPORT_CONTENT', 'created_month_body', 'created_month_body_block' );
$this->setBlock('REPORT_CONTENT', 'submitted_month_body', 'submitted_month_body_block' );
$this->setBlock('REPORT_CONTENT', 'returned_month_body', 'returned_month_body_block' );

$months = $start->diffInMonths($end);

/** @var Alaress_Date_Format $currentMonth   */
$currentMonth = clone $start;

$report = new Schoolbox\Engagement\DueWork();

for($i=0; $i < $months; $i++) {

    $this->setVar('MONTH', $currentMonth->getUsingFormat('F Y'));
    $this->parse('month_head_block', 'month_head', true);


    $endMonth = clone $currentMonth;
    $endMonth->endOfMonth();


    $created = $report->created($currentMonth, $endMonth);
    $submitted = $report->submitted($currentMonth, $endMonth);
    $returned = $report->returned($currentMonth, $endMonth);

    $this->setVar('CREATED_MONTH_COUNT', $created);
    $this->parse('created_month_body_block', 'created_month_body', true);

    $this->setVar('SUBMITTED_MONTH_COUNT',$submitted);
    $this->parse('submitted_month_body_block', 'submitted_month_body', true);

    $this->setVar('RETURNED_MONTH_COUNT', $returned);
    $this->parse('returned_month_body_block', 'returned_month_body', true);

    $currentMonth->addMonths(1);
}