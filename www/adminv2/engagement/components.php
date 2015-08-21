<?php
/**
 * Display due work
 */

$this->setBlock('REPORT_CONTENT', 'component', 'component_block' );

$months = $start->diffInMonths($end);

/** @var Alaress_Date_Format $currentMonth   */
$currentMonth = clone $start;

$report = new Schoolbox\Engagement\Component();

$output = $report->componentInstances();

foreach($output as $row ) {

    $this->setVar(array('COMPONENT_NAME' => $row['name'],
                        'COMPONENT_COUNT' => $row['total'],));

    $this->parse('component_block','component',true);
}

