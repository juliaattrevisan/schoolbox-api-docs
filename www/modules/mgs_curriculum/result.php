<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once 'synergetic-curriculum.php';

$page = new SitePage;

$synCurric = new SynergeticCurriculum();
if($_GET['keyword']){
    $page->setFile(TEMPLATE_content, 'modules/mgs_curriculum/list.html');
    $page->setBlock(TEMPLATE_content, 'result', 'result_block');
    $results = $synCurric->search($_GET['keyword']);
    //print_r($results);
    foreach($results as $subject) {
        $output = array('SEARCH_RESULT_NAME' => $subject['SubjectAssessmentsCode'],
                        'SEARCH_RESULT_CODE'    => $subject['SubjectAssessmentsCode']);
        $page->setVar($output);
        $page->parse('result_block','result',true);
    }

    $page->outputPage();
}
?>
