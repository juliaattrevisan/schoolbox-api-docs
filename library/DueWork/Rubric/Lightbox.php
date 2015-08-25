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

    /**
     * Get index of gradient from a percentage value
     *
     * @param $top
     * @param $bottom
     * @return int
     */
    private function getGradientIndex($top, $bottom)
    {
        if (!$bottom) {
            $urgency = 0;
        }
        else {
            $urgency = ceil(10 * $top/$bottom);
        }

        return max(0, min(10, $urgency));
    }

    /**
     * Render
     *
     * @param $templateHandle
     * @param $submissionBoxID
     * @param $studentID
     * @param bool $showMarks
     * @param bool $editable
     */
    public function render($templateHandle, $submissionBoxID, $studentID, $showMarks = true, $editable = false) {

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

        $rubricMark = new DueWork_Rubric_Mark($rubric);
        $rubricMark->populateMarks($studentID);

        $data = $rubricMark->toArray();

        $json = array();
        $legs = array();

        for($i = 0; $i < count($data['criteria']); ++$i) {
            $json[] = $data['criteria'][$i]['value'] < 0 ? 0 : (float)$data['criteria'][$i]['value'];
            $legs[] = $data['criteria'][$i]['label'];
        }

        $page->setBlock($templateHandle, 'rubric_rating', 'rubric_rating_block');
        $page->setBlock($templateHandle, 'rubric_crit', 'rubric_crit_block');
        $page->setBlock($templateHandle, 'rubric_display', 'rubric_display_block');
        $page->setBlock('rubric_crit', 'rubric_crit_rating', 'rubric_crit_rating_block');
        $page->setBlock('rubric_crit', 'rubric_grouping', 'rubric_grouping_block');

        $colCount = count($data['criteria'][0]['results']);

        for($i = 0; $i < $colCount; ++$i) {
            $page->parse('rubric_rating_block', 'rubric_rating', true);
        }

        for($i = 0; $i < count($data['criteria']); ++$i) {
            // display a grouping row
            if($i == 0 || $data['criteria'][$i]['group'] != $data['criteria'][$i - 1]['group']) {
                $page->setVar(array(
                    'RUBRIC_GROUP_SPAN' => $colCount + 1,
                    'RUBRIC_GROUP_LABEL' => $data['criteria'][$i]['group']
                ));
                $page->parse('rubric_grouping_block','rubric_grouping', false);
            }
            else {
                $page->setVar('rubric_grouping_block', '');
            }

            $page->setVar(array(
                'rubric_crit_rating_block' => '',
                'RUBRIC_CRIT_LABEL' => $data['criteria'][$i]['label'],
                'RUBRIC_CRIT_NAME' => preg_replace("#[^a-z0-9\-]#is", '', strtolower($data['criteria'][$i]['label'])),
                'RUBRIC_CRIT_INDEX' => $i,
                'RUBRIC_CRIT_ID' => $data['criteria'][$i]['id'],
                'RUBRIC_CRIT_EDITABLE' => $editable ? '' : 'disabled="disabled"'
            ));

            foreach($data['criteria'][$i]['results'] as $value => $descriptor) {
                $page->setVar(array(
                    'RUBRIC_CRIT_RATING_LABEL' => $descriptor,
                    'RUBRIC_CRIT_RATING_VALUE' => $value,
                    'RUBRIC_CRIT_RATING_CLASS' => 'lol',
                    'RUBRIC_CRIT_RATING_SELECTED' => $data['criteria'][$i]['value'] == $value ? 'checked="checked"' : '',
                ));
                $page->parse('rubric_crit_rating_block', 'rubric_crit_rating', true);
            }

            $page->parse('rubric_crit_block', 'rubric_crit', true);
        }

        // calculate scores
        $sum = 0;
        $max = 0;
        for($i = 0; $i < count($data['criteria']); ++$i) {
            $sum += $data['criteria'][$i]['value'] > 0 ? $data['criteria'][$i]['value'] : 0;
            $max += count($data['criteria'][0]['results']) - 1;
        }

        $gradientIndex = $this->getGradientIndex($sum, $max);

        if ($showMarks) {
            $page->setVar(array(
                'GRAPH_DATA' => json_encode($json),
                'GRAPH_LEGS' => json_encode($legs),
                'GRAPH_MAX'  => count($data['criteria'][0]['results']) - 1,
                'RUBRIC_GRADE_SUM' => $sum,
                'RUBRIC_GRADE_MAX' => $max,
                'RUBRIC_GRADIENT' => $gradientIndex,
            ));
            $page->parse('rubric_display_block', 'rubric_display');
        } else {
            $page->setVar(array(
                'GRAPH_DATA' => json_encode(array()),
                'GRAPH_LEGS' => json_encode(array()),
                'GRAPH_MAX'  => 0,
                'RUBRIC_GRADE_SUM' => 0,
                'RUBRIC_GRADE_MAX' => 0,
                'RUBRIC_GRADIENT' => $gradientIndex,
            ));
            $page->setVar('rubric_display_block', '');
        }
    }
}
