<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';

$this->setFile(TEMPLATE_content, 'submission/listSubmission.html');
$this->setTitle('List of Submission Boxes');
$this->setTab('submission');
$this->setBlock(TEMPLATE_content, 'submission_block', 'submission_listing');
$this->setBlock(TEMPLATE_content, 'category_block', 'category_listing');

$submissionHandler = new Submission;
$subList = $submissionHandler->getAllForUser($_SESSION['id']);

if(is_array($subList))
{
    foreach($subList as $sub) {
        if($prevCat['catid'] != $sub['catid'] && $prevCat['catid'] != '') {
            if($prevCat['code'] != "")
                    $this->setVar('CATEGORY', $prevCat['name']." (".$prevCat['code'].")");
            else
                    $this->setVar('CATEGORY', $prevCat['name']);

            $this->parse('category_listing', 'category_block', true);
            $this->setVar('submission_listing', '');
            $i = 0;

        }

        $totals = $submissionHandler->getBoxCount($sub['id']);


        $output = array('ID'        => $sub['id'],
                        'ASSDESC'   => scrubTEXT($sub['description']),
                        'TYPE'      => $sub['type'],
                        'JAVAASSDESC'=> addslashes(scrubTEXT($sub['description'])),
                        'OPENDATE'  => $sub['open_date'],
                        'DUEDATE'   => $sub['date'],
                        'MARKED'    => $totals['marked'],
                        'UNMARKED'  => ($totals['total']-$totals['marked']),
                        'TOTAL'     => $totals['total'],
                        'ON_OFF'    => ++$i%2?'':'dark',
                        );
        $this->setVar($output);
        $this->parse('submission_listing','submission_block',true);

        $prevCat = $sub;

    }

    if($prevCat['code'] != "")
        $this->setVar("CATEGORY", $prevCat['name']." (".$prevCat['code'].")");
    else
        $this->setVar("CATEGORY", $prevCat['name']);

    $this->parse("category_listing", "category_block", true);

}
else
    $this->setVar('category_listing','')

?>
