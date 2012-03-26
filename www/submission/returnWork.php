<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submissionReturn.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/user.php';

$this->setFile(TEMPLATE_content, 'submission/returnWork.html');
$this->setBlock(TEMPLATE_content, 'mark_type_percent', 'mark_type_percent_block');
$this->setBlock(TEMPLATE_content, 'mark_type_letter', 'mark_type_letter_block');
$this->setBlock(TEMPLATE_content, 'mark_type_raw', 'mark_type_raw_block');
$this->setTitle('Return Student Work');
$this->setTab('submission');

$subHandler = new Submission;
$userHandler = new User;

if(is_numeric($this->arg[0]) && is_numeric($this->arg[1]) ) {

    $subbox = $subHandler->getByID($this->arg[0]);
    $user = $userHandler->getByID($this->arg[1]);
    // $subReturn = $subReturnHandler->getOneByUser($this->arg[1], $this->arg[0]);

    //Check user owners box they are returning to
    if($subbox['owner'] != $_SESSION['id'])
            error(25);

    $output = array('CATEGORY'  => Scrub::text($subbox['categoryName']),
                    'ASSNAME'   => Scrub::text($subbox['name']),
                    'BOX_ID'    => $this->arg[0],
                    'NAME'      => $userHandler->formatName($user),
                    'UID'       => $this->arg[1],
                );

    $this->setVar($output);

    $marksheet = Marksheet_Factory::build($subbox['mark_type_slug']);
    $mark = $marksheet->retrieveMark($this->arg[1], $this->arg[0]);

    $this->setVar('MARK_TYPE_COMMENT', $marksheet->getComment());

    // custom code for each marktype
    switch($marksheet->getSlug()) {
        case 'raw':
            $output = array(
                'MAX_MARK' => $mark['max_mark'],
                'MARK'     => $mark['mark'],
                'COMMENT'  => $mark['comment']
            );
            $this->setVar($output);
            break;

        case 'letter':
            $this->dropdown('grade_drop', $subHandler->getLetterGrades(), $mark['grade_selected'], 'mark_type_letter', array(
                'grade_id' => 'GRADE_VALUE',
                'caption'  => 'GRADE_TEXT',
            ));
            $this->setVar('COMMENT', $mark['comment']);
            break;

        case 'percent':
            $output = array(
                'MARK'     => $mark['mark'],
                'COMMENT'  => $mark['comment']
            );
            $this->setVar($output);
            break;
        default:
            error(-1);
            break;
    }

    $marktypes = $subHandler->getMarkTypes();

    // parse the appropraite block, and hide the rest
    foreach($marktypes as $type) {
        if($type['mark_type_id'] == $marksheet->getID()) {
            $this->parse("mark_type_{$type['slug']}_block", "mark_type_{$type['slug']}", false);

        } else
        $this->setVar("mark_type_{$type['slug']}_block",'');
    }






    /* TODO: Add code to output already marked details */


}
else
    error(2);


?>
