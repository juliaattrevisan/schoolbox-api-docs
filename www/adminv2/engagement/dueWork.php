<?php
/**
 * Display due work
 */

$this->setBlock('REPORT_CONTENT', 'month_head', 'month_head_block' );
$this->setBlock('REPORT_CONTENT', 'created_month_body', 'created_month_body_block' );
$this->setBlock('REPORT_CONTENT', 'submitted_month_body', 'submitted_month_body_block' );
$this->setBlock('REPORT_CONTENT', 'returned_month_body', 'returned_month_body_block' );
$this->setBlock('REPORT_CONTENT', 'visibility', 'visibility_block' );
$this->setBlock('visibility', 'visibility_month', 'visibility_month_block' );


$months = $start->diffInMonths($end);

/** @var Alaress_Date_Format $currentMonth   */
$currentMonth = clone $start;

$report = new Schoolbox\Engagement\DueWork();

$visibilityLookup = array('Staff Only','Staff and Students', 'Staff, Students and Parents');

foreach($visibilityLookup as $visibility) {
    $visibilityTable[$visibility] = array_fill(0, $months, 0);
}

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

    $visibility = $report->visibility($currentMonth, $endMonth);
    foreach($visibility as $row) {
        $visibilityTable[$visibilityLookup[$row['mark_visibility']]][$i] = $row['total'];
    }

    $currentMonth->addMonths(1);
}

foreach($visibilityTable as $visibility => $monthTotal) {

    $this->setVar('VISIBILITY',$visibility);

    foreach($monthTotal as $total) {
        $this->setVar('VISIBILITY_COUNT', $total);
        $this->parse('visibility_month_block','visibility_month',true);
    }

    $this->parse('visibility_block', 'visibility', true);
    $this->setVar('visibility_month_block','');

}