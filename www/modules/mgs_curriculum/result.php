<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once 'synergetic-curriculum.php';

$page = new SitePage;
$groupHandler = new Group;

if ($groupHandler->isStaff()) {
    $synCurric = new SynergeticCurriculum();
    if ($_GET['keyword']) {
        $page->setFile(TEMPLATE_content, 'modules/mgs_curriculum/list.html');
        $page->setBlock(TEMPLATE_content, 'result', 'result_block');
        $results = $synCurric->search($_GET['keyword']);
        //print_r($results);
        if (is_array($results)) {
            foreach ($results as $subject) {
                $output = array(
                    'SEARCH_RESULT_NAME' => $subject['Heading'] . ' - ' . $subject['UnitName'] . ' (Year ' . $subject['YearLevel'] . ')',
                    'SEARCH_RESULT_CODE' => $subject['SubjectAssessmentsCode'] . '/' . $subject['computed']
                );
                $page->setVar($output);
                $page->parse('result_block', 'result', true);
            }
        } else {
            $page->setVar(
                'result_block', '</ul>
            <p>Sorry, but your search for ' . Scrub::text(
                $_GET['keyword']
            ) . ' didn\'t return any results. Please press back and try again.</p><ul>'
            );
        }

        $page->outputPage();
    }
} else {
    error(19);
}
?>