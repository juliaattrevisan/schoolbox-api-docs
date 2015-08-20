<?php

$this->setBlock('REPORT_CONTENT', 'month_head', 'month_head_block' );
$this->setBlock('REPORT_CONTENT', 'video_user_role', 'video_user_role_block' );
$this->setBlock('video_user_role', 'video_user_role_month', 'video_user_role_month_block' );
$this->setBlock('REPORT_CONTENT', 'image_user_role', 'image_user_role_block' );
$this->setBlock('image_user_role', 'image_user_role_month', 'image_user_role_month_block' );


$months = $start->diffInMonths($end);

/** @var Alaress_Date_Format $currentMonth   */
$currentMonth = clone $start;

$report = new Schoolbox\Engagement\CMS();

$roleMapper = new \Schoolbox\RoleMapper();
$roleList = $roleMapper->findAll();

foreach($roleList as $role) {
    $videoTable[$role->getName()] = array_fill(0,$months, 0);
    $imageTable[$role->getName()] = array_fill(0,$months, 0);

}

for($i=0; $i < $months; $i++) {

    $this->setVar('MONTH', $currentMonth->getUsingFormat('F Y'));
    $this->parse('month_head_block', 'month_head', true);


    $endMonth = clone $currentMonth;
    $endMonth->endOfMonth();

    $video = $report->logFileType($currentMonth, $endMonth, 'access', 'video');
    foreach($video as $row) {
        $videoTable[$row['name']][$i] = $row['total'];
    }

    $image = $report->log($currentMonth, $endMonth, 'access', 'image');
    foreach($image as $row) {
        $imageTable[$row['name']][$i] = $row['total'];
    }

    $currentMonth->addMonths(1);
}

foreach($videoTable as $role => $monthTotal) {

    $this->setVar('USER_ROLE',$role);

    foreach($monthTotal as $total) {
        $this->setVar('VIDEO_MONTH_COUNT', $total);
        $this->parse('video_user_role_month_block','video_user_role_month',true);
    }

    $this->parse('video_user_role_block', 'video_user_role', true);
    $this->setVar('video_user_role_month_block','');

}

foreach($imageTable as $role => $monthTotal) {

    $this->setVar('USER_ROLE',$role);

    foreach($monthTotal as $total) {
        $this->setVar('IMAGES_MONTH_COUNT', $total);
        $this->parse('image_user_role_month_block','image_user_role_month',true);
    }

    $this->parse('image_user_role_block', 'image_user_role', true);
    $this->setVar('image_user_role_month_block','');

}