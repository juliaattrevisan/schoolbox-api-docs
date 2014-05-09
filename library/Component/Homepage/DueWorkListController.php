<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Collection;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;

class Component_Homepage_DueWorkListController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var Collection|SubmissionBox[]
     */
    private $submissionList;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        $this->groupHandler = new Group();
        $options = array(
            'order' => array('date' => 'asc'),
        );
        $size = $this->getConfig('size');
        if ($size > 0) {
            $options['limit'] = $size;
        }
        $this->submissionList = SubmissionBoxMapper::getInstance()->findByColumn(
            'category',
            $this->getContext('id'),
            $options
        );
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'dueWorkList')) {
            return false;
        }


        $this->parseConfig();

        /* Tick for checkbox which controls whether to show the Posted By details
        */
        if ($this->getConfig('compactMode') == 1) {
            $this->setVar(array(
                'COMPONENT_COMPACTMODE' => 'compact-mode',
                'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => 'checked="checked"'
            ));
        }
        else {
            $this->setVar(array(
                'COMPONENT_COMPACTMODE' => '',
                'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => ''
            ));
        }

        //due output
        $this->setBlock($templateHandle, 'duework', 'duework_block');
        $this->setBlock('duework', 'duework_result_link', 'duework_result_link_block');
        $this->setBlock('duework', 'duework_due_date', 'duework_due_date_block');
        $this->setBlock('duework', 'duework_actions', 'duework_actions_block');
        $this->setBlock($templateHandle, 'allwork_button', 'allwork_button_block');
        $this->setBlock($templateHandle, "submit_dw", "submit_dw_block");

        $j = 0;

        $this->setVar('duework_block', '');

        // We need the count of returns and submissions within the submission
        // boxes that have been retrieved.
        $return_counts     = array();
        $submission_counts = array();
        if ($this->groupHandler->isStudent()) {
            $box_id_list = $this->submissionList->getColumnValues('id');
            $return_counts = SubmissionBox\ReturnedWorkMapper::getInstance()->findCountsForSubmissionBoxes(
                $box_id_list, $_SESSION['id']
            );
            $submission_counts = SubmissionBox\SubmissionMapper::getInstance()->findCountsForSubmissionBoxes(
                $box_id_list, $_SESSION['id']
            );
        }
        $time = new Alaress_Date_Format(new DateTime());

        foreach ($this->submissionList as $submission) {

            // if the late date has passed, then don't display this item (to staff or students)
            // unless it's a task.
            $isTask = $submission->getTask()->getId();
            if (!$isTask && $submission->getLateDate()->getDateTimeObject() < $time->getDateTimeObject()) {
                continue;
            }

			/* If something was submitted to this box, hide it from the component
			 */
			if ($submission_counts[$submission->getId()]) {
				continue;
			}

            $box_id = $submission->getId();

            $displayActions = false;
            $displayResults = false;
            $displayDueDate = !$isTask;

            /* Actions Block */

            $actionURL = null;
            $nameHTML  = null;
            $dateHTML  = null;
            $dueActionName = '';
            $dueActionIcon = '';
            if ($submission->getTask()->getId()) {
                $nameHTML = Scrub::text($submission->getName());
                $dateHTML = '';
            }
            else {
                $nameHTML = ($actionURL ? '<a href="'.$actionURL.'">' : "") . Scrub::text($submission->getName()) . ($actionURL ? '</a>' : "");
                $dateHTML = $submission->getDueDate()->getDateOnly();
            }

            // what to display if student
            $this->setVar('duework_result_link_block', '');
            $this->setVar('duework_actions_block', '');
            $this->setVar('duework_due_date_block', '');
            if ($this->groupHandler->isStudent()) {
                if (!$isTask) {
                    $displayActions = true;
                    $actionURL = '/submission/submissionHistory/' . $box_id .'/'. $_SESSION['id'];

                    // if a mark has been given, regardless of all other detail, display "View Mark"
                    if (!empty($return_counts[$box_id])) {
                        $dueActionName = 'View Mark';
                        $dueActionIcon = 'go-to';
                    }
                    // no mark has been given
                    else {
                        // if dueWork is accepting submissions
                        if ($submission->getAcceptOnline()) {
                            // if something has been submitted
                            if (!empty($submission_counts[$box_id])) {
                                $dueActionName = 'Awaiting Mark';
                                $dueActionIcon = 'go-to';
                            }
                            // nothing has been submitted
                            else {
                                // if the submission box is open
                                // (Check submission number)
                                if ($time->getDateTimeObject() > $submission->getOpenDate()->getDateTimeObject()
                                    && $time->getDateTimeObject() < $submission->getLateDate()->getDateTimeObject()
                                ) {
                                    $dueActionName = 'Submit Work';
                                    $dueActionIcon = 'go-to';
                                }
                                // if submission box is closed
                                else {
                                    $dueActionName = 'View Details';
                                    $dueActionIcon = 'go-to';
                                }
                            }
                        }
                        // dueWork not accepting submissions
                        else {
                            $dueActionName = 'View Details';
                            $dueActionIcon = 'go-to';
                        }
                    }
                }
            }

            // what to display if staff
            elseif ($this->groupHandler->isSuperuser() || $submission->getOwner()->getId() == $_SESSION['id']) {
                if (!$isTask) {
                    $displayResults = true;
                }
                $actionURL = '/submission/createSubmission/modify/'.$box_id;
                $dueActionName = 'Modify';
                $dueActionIcon = 'modify';
                $displayActions = true;
            }

            $output = array(
                'DUE_ID'      => $box_id,
                'DUE_ACTION'  => $actionURL,
                'DUE_ACTION_NAME' => $dueActionName,
                'DUE_ACTION_ICON' => $dueActionIcon,
                'DUE_NAME'    => $nameHTML,
                'DUE_TYPE'    => Scrub::text($submission->getWorkType()->getName()),
                'DUE_DATE'    => $dateHTML,
                'DUE_COMMENT' => Scrub::html($submission->getDescription()),
                'ON_OFF'      => ++$j % 2 ? '' : 'dark',
            );
            $this->setVar($output);
            if ($displayDueDate) {
                $this->parse('duework_due_date_block', 'duework_due_date');
            }
            else {
                $this->setVar('duework_due_date_block', '');
            }
            if ($displayActions) {
                $this->parse('duework_actions_block', 'duework_actions');
            }
            else {
                $this->setVar('duework_actions_block', '');
            }
            if ($displayResults) {
                $this->parse('duework_result_link_block', 'duework_result_link');
            }
            else {
                $this->setVar('duework_result_link_block', '');
            }
            $this->parse("duework_block", "duework", true);
        }

        //setup block for submission button
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("submit_dw_block", "");
        }
        else {
            $this->parse("submit_dw_block", "submit_dw");
        }

        // if the user is SOMEHOW involved in this category...
        $this->setVar('allwork_button_block', '');
        $this->setVar(array(
                'WORK_YEAR' => date('Y'),
                'WORK_SEMESTER' => DueWork_All::getSemester()
        ));


        if($this->groupHandler->isStudent()) {

            // is the current user a member of this category?
            $catAccess = $this->groupHandler->involvedInGroup($_SESSION['id'], $this->getContext('id'));
            $subAccess = false;
            if($this->getContext('code')) {
                $subAccess = $this->groupHandler->involvedInSubject($this->getContext('code'));
            }

            if($catAccess || $subAccess ) {
                $this->parse('allwork_button_block', 'allwork_button');
            }
        }

        return true;
    }

}
