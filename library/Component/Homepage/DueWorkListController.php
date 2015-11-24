<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;

class Component_Homepage_DueWorkListController extends Component_Homepage_ControllerAbstract
{

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
    public function execute()
    {
        $this->groupHandler = new Group();

        $this->submissionList = new Collection(
            SubmissionBoxMapper::getInstance()
                ->getUpcomingActivities(null, $this->getConfig('size'), $this->getContext()->getId())
                ->getColumnValues('submissionBox')
        );
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     *
     * @param String $templateHandle
     *
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'dueWorkList')) {
            return false;
        }


        $this->parseConfig();

        /* Tick for checkbox which controls whether to show the Posted By details
        */
        if ($this->getConfig('compactMode') == 1) {
            $this->setVar(
                array(
                    'COMPONENT_COMPACTMODE'                 => 'compact-mode',
                    'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => 'checked="checked"'
                )
            );
        } else {
            $this->setVar(
                array(
                    'COMPONENT_COMPACTMODE'                 => '',
                    'COMPONENT_CONFIG_COMPACTMODE_SELECTED' => ''
                )
            );
        }

        //due output
        $this->setBlock($templateHandle, 'duework', 'duework_block');
        $this->setBlock('duework', 'duework_due_date', 'duework_due_date_block');
        $this->setBlock('duework', 'duework_result_link', 'duework_result_link_block');
        $this->setBlock('duework', 'duework_actions', 'duework_actions_block');
        $this->setBlock($templateHandle, 'allwork_button', 'allwork_button_block');
        $this->setBlock($templateHandle, "submit_dw", "submit_dw_block");

        $j = 0;

        $this->setVar('duework_block', '');

        // We need the count of returns and submissions within the submission
        // boxes that have been retrieved.
        $submission_counts = array();
        $isStudent         = $this->groupHandler->isStudent();
        $isStaff           = $this->groupHandler->isStaff();
        $userId            = $_SESSION['id'];
        if ($isStudent) {
            $box_id_list       = $this->submissionList->getColumnValues('id');
            $submission_counts = SubmissionBox\Submission\FileMapper::getInstance()->findCountsForSubmissionBoxes(
                $box_id_list,
                $userId
            );
        }
        $time = new Alaress_Date_Format(new DateTime());

        foreach ($this->submissionList as $submission) {
            // if the late date has passed, then don't display this item (to staff or students)
            // unless it's a task.
            $isTask = $submission->getTask()->getId();
            if (!$isTask
                && $submission->getLateDate() instanceof Alaress_Date_Format
                && $submission->getLateDate()->getDateTimeObject() < $time->getDateTimeObject()
            ) {
                continue;
            }

            // Don't show unpublished work to non-staff,
            // but allow it to always show to owner
            if (!$isTask
                && $submission->getPublishDate() instanceof Alaress_Date_Format
                && $submission->getPublishDate()->getDateTimeObject() > $time->getDateTimeObject()
            ) {
                if (!($isStaff || $submission->getOwner()->getId() == $userId)) {
                    continue;
                }
            }

            /* If student can no longer submit to this box,
             * hide it from the component
             */
            if ($submission_counts[$submission->getId()]
                && !$submission->getAcceptMultiple()
            ) {
                continue;
            }

            $box_id = $submission->getId();

            $displayActions = false;
            $displayResults = false;
            $displayDueDate = !$isTask;

            /* Actions Block */

            $actionURL     = null;
            $nameHTML      = Scrub::text($submission->getName());
            $dateHTML      = null;
            $dueActionName = '';
            $dueActionIcon = '';
            if ($submission->getTask()->getId()) {
                $dateHTML = '';
            } else {
                // Show due time if there is one
                if ($submission->getDueDate()->isAtEndOfDay()) {
                    $dateHTML = $submission->getDueDate()->getDateOnly();
                } else {
                    $dateHTML = $submission->getDueDate()->getShort();
                }
            }

            $this->setVar('duework_result_link_block', '');
            $this->setVar('duework_actions_block', '');
            $this->setVar('duework_due_date_block', '');

            if ($this->groupHandler->isStudent()) {
                // what to display if student
                if (!$isTask) {
                    $displayActions = false;
                    $actionURL      = '/submission/submissionHistory/' . $box_id . '/' . $_SESSION['id'];
                }
            } elseif ($this->permission >= WRITE_ACCESS && $this->groupHandler->isStaff()) {
                // what to display if staff
                if (!$isTask) {
                    $displayResults = true;
                }
                $actionURL      = '/submission/createSubmission/modify/' . $box_id;
                $dueActionName  = 'Modify';
                $dueActionIcon  = 'modify';
                $displayActions = true;
            }

            $relative = new \Alaress_Date_Format($submission->getDueDate(), 'Y-m-d H:i:s');

            $output = array(
                'DUE_ID'            => $box_id,
                'DUE_ACTION'        => $actionURL,
                'DUE_ACTION_NAME'   => $dueActionName,
                'DUE_ACTION_ICON'   => $dueActionIcon,
                'DUE_NAME'          => $nameHTML,
                'DUE_DATE'          => $dateHTML,
                'DUE_COMMENT'       => Scrub::html($submission->getDescription()),
                'ON_OFF'            => ++$j % 2 ? '' : 'dark',
                'WORK_URGENCY'      => $submission->getGradientIndex(),
                'DUE_DATE_RELATIVE' => $relative->getRelativeTime(),
            );
            $this->setVar($output);
            if ($displayDueDate) {
                $this->parse('duework_due_date_block', 'duework_due_date');
            } else {
                $this->setVar('duework_due_date_block', '');
            }
            if ($displayActions) {
                $this->parse('duework_actions_block', 'duework_actions');
            } else {
                $this->setVar('duework_actions_block', '');
            }
            if ($displayResults) {
                $this->parse('duework_result_link_block', 'duework_result_link');
            } else {
                $this->setVar('duework_result_link_block', '');
            }

            $this->parse("duework_block", "duework", true);
        }

        //setup block for submission button
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("submit_dw_block", "");
        } else {
            $this->parse("submit_dw_block", "submit_dw");
        }

        // if the user is SOMEHOW involved in this category...
        $this->setVar('allwork_button_block', '');
        $this->setVar(
            array(
                'WORK_YEAR'     => date('Y'),
                'WORK_SEMESTER' => DueWork_All::getSemester()
            )
        );

        // Allow students or staff acting as students to see the view results buttons.
        // (Staff have no context for actually following the view result button)
        if ($this->permission == READ_ACCESS) {
            // is the current user a member of this category?
            $catAccess = $this->groupHandler->involvedInGroup($_SESSION['id'], $this->getContext()->getId());
            $subAccess = false;
            if ($this->getContext()->getCodeString()) {
                $subAccess = $this->groupHandler->involvedInSubject($this->getContext()->getCodeString());
            }

            if ($catAccess || $subAccess) {
                $this->parse('allwork_button_block', 'allwork_button');
            }
        }

        return true;
    }
}
