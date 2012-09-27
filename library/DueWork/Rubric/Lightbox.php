<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DueWork_Rubric_Lightbox {

    private $page;
    private $rubricHandler;

    const RUBRIC_UNKNOWN = -1;
    const RUBRIC_DESCRIPTOR = 0;
    const RUBRIC_RESULTSET = 1;

    public function __construct(SitePage $page) {
        $this->page = $page;
        $this->rubricHandler = new DueWork_Rubric_Handler();
    }

    public function render($templateHandle, $submissionBoxID, $studentID) {

        // make sure it actually has permission to render
        // to see this, you have to be the student in question, a parent of the student in question, or staff
        $groupHandler = new Group();

        if($groupHandler->isStaff()) {
            // allow
        } elseif($groupHandler->isParentOfChild($studentID)) {
            // allow
        } elseif($_SESSION['id'] == $studentID) {
            // allow
        } else {
            error(17);
        }

        $page = $this->page;
        $page->setFile($templateHandle, 'submission/rubricLightbox.html');

        $rubric = $this->rubricHandler->getRubric($submissionBoxID);

        $rType = self::RUBRIC_UNKNOWN;
        if($rubric instanceof DueWork_Rubric_ResultSetRubric) {
            $rType = self::RUBRIC_RESULTSET;
        }
        elseif($rubric instanceof DueWork_Rubric_DescriptorRubric) {
            $rType = self::RUBRIC_DESCRIPTOR;
        }
        else {
            return;
        }

        $rubricMark = new DueWork_Rubric_Mark($rubric);
        $rubricMark->populateMarks($studentID);

        $data = $rubricMark->toArray();

        $json = array();
        $legs = array();

        for($i = 0; $i < count($data['criteria']); ++$i) {
            $json[] = array($i, $data['criteria'][$i]['value'] < 0 ? 0 : $data['criteria'][$i]['value']);
            $legs[] = array('label' => $data['criteria'][$i]['label']);
        }

        $page->setBlock($templateHandle, 'rubric_grouping', 'rubric_grouping_block');
        $page->setBlock('rubric_grouping', 'rubric_crit', 'rubric_crit_block');
        $page->setBlock('rubric_crit', 'rubric_crit_descriptor', 'rubric_crit_descriptor_block');
        $page->setBlock('rubric_crit', 'rubric_crit_resultset', 'rubric_crit_resultset_block');

        for($i = 0; $i < count($data['criteria']); ++$i) {
            // display a grouping
            if($data['criteria'][$i]['group'] != $data['criteria'][$i - 1]['group']) {
                if($i != 0) {
                    $page->parse('rubric_grouping_block','rubric_grouping', true);
                    $page->setVar('rubric_crit_block', '');
                }
                $page->setVar(array(
                    'RUBRIC_GROUP_LABEL' => $data['criteria'][$i]['group']
                ));
            }

            $page->setVar(array(
                'RUBRIC_CRIT_LABEL'        => $data['criteria'][$i]['label'],
                'RUBRIC_CRIT_RATING_LABEL' => $data['criteria'][$i]['results'][$data['criteria'][$i]['value']]
            ));

            if($rType == self::RUBRIC_DESCRIPTOR) {
                $page->parse('rubric_crit_descriptor_block', 'rubric_crit_descriptor');
                $page->setVar('rubric_crit_resultset_block', '');
            }
            else {
                $page->parse('rubric_crit_resultset_block', 'rubric_crit_resultset');
                $page->setVar('rubric_crit_descriptor_block', '');
            }
            $page->parse('rubric_crit_block', 'rubric_crit', true);

        }
        $page->parse('rubric_grouping_block', 'rubric_grouping', true);

        // calculate scores
        $sum = 0;
        $max = 0;
        for($i = 0; $i < count($data['criteria']); ++$i) {
            $sum += $data['criteria'][$i]['value'];
            $max += count($data['criteria'][0]['results']) - 1;
        }

        $page->setVar(array(
            'GRAPH_DATA' => json_encode($json),
            'GRAPH_LEGS' => json_encode($legs),
            'GRAPH_MAX'  => count($data['criteria'][0]['results']) - 1,
            'RUBRIC_GRADE_SUM' => $sum,
            'RUBRIC_GRADE_MAX' => $max,
        ));


    }
}
