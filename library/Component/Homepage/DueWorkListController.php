<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_DueWorkListController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var Submission
     */
    private $submissionHandler;

    /**
     * @var array
     */
    private $submissionList;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        $this->submissionHandler = new Submission;
        $this->groupHandler = new Group();
        $this->submissionList = $this->submissionHandler->getAllForCategory($this->getContext('id'), array('size' => $this->getConfig('size')), true);
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
        $this->setBlock($templateHandle, 'allwork_button', 'allwork_button_block');


        if (is_array($this->submissionList)) {

            $j = 0;

            foreach($this->submissionList as $submission) {

                $date = new Alaress_Date_Format($submission['date'], 'U');
                $dateHTML = $date->getDateOnly();


                /* Actions Block */
                $this->setBlock('duework', 'duework_actions', 'duework_actions_block');

                $actionURL = null;

                // what to display if student
                if($this->groupHandler->isStudent()) {
                    $actionURL = '/submission/submissionHistory/' . $submission['id'] .'/'. $_SESSION['id'];


                    // if a mark has been given, regardless of all other detail, display "View Mark"
                    if (!empty($submission['returned'])) {
                        $this->setVar('DUE_ACTION_NAME', 'View Mark');
                    }
                    // no mark has been given
                    else {
                        // if dueWork is accepting submissions
                        if($submission['accept_submission']) {
                            // if something has been submitted
                            if (!empty($submission['submitted'])) {
                                $this->setVar('DUE_ACTION_NAME', 'Awaiting Mark');
                            }
                            // nothing has been submitted
                            else {
                                // if the submission box is open
                                if (time() > $submission['open'] && time() < $submission['late']) {
                                    $this->setVar('DUE_ACTION_NAME', 'Submit Work');
                                }
                                // if submission box is closed
                                else {
                                    $this->setVar('DUE_ACTION_NAME', 'View Details');
                                }
                            }
                        }
                        // dueWork not accepting submissions
                        else {
                            $this->setVar('DUE_ACTION_NAME', 'View Details');
                        }
                    }

                    $this->setVar('DUE_ACTION', $actionURL);
                    $this->parse('duework_actions_block', 'duework_actions');
                }

                // what to display if staff
                elseif ($this->groupHandler->isStaff() && $submission['owner'] == $_SESSION['id']) {
                    $actionURL = '/submission/viewSubmission/' . $submission['id'];
                    $this->setVar('duework_actions_block', '<a href="/submission/createSubmission/modify/'.$submission['id'].'">Modify</a>');
                }
                // if anybody else, don't display anything
                else {
                    $this->setVar('duework_actions_block', '');
                }

                $nameHTML = ($actionURL ? '<a href="'.$actionURL.'">' : "") . Scrub::text($submission['name']) . ($actionURL ? '</a>' : "");

                $output = array(
                    'DUE_ID'      => $submission['id'],
                    'DUE_ACTION'  => $actionURL,
                    'DUE_NAME'    => $nameHTML,
                    'DUE_TYPE'    => Scrub::text($submission['typeName']),
                    'DUE_DATE'    => $dateHTML,
                    'DUE_COMMENT' => Scrub::html($submission['description']),
                    'ON_OFF'      => ++$j % 2 ? '' : 'dark',
                );

                $this->setVar($output);
                $this->parse("duework_block", "duework", true);
            }
        } else
            $this->setVar('duework_block', '');

        //setup block for submission button
        $this->setBlock($templateHandle, "submit_dw", "submit_dw_block");
        if($this->permission < WRITE_ACCESS)
            $this->setVar("submit_dw_block", "");
        else
            $this->parse("submit_dw_block", "submit_dw");

        // if the user is SOMEHOW involved in this category...
        $this->setVar('allwork_button_block', '');

        if($this->groupHandler->isStudent()) {

            // is the current user a member of this category?
            $catAccess = $this->groupHandler->involvedInGroup($_SESSION['id'], $this->getContext('id'));
            $subAccess = false;
            if($this->getContext('code')) {
                $subAccess = $this->groupHandler->involvedInSubject($this->getContext('code'));
            }

            if($catAccess || $subAccess ) {
                $this->setVar(array(
                    'WORK_YEAR' => date('Y'),
                    'WORK_SEMESTER' => DueWork_All::getSemester()
                ));
                $this->parse('allwork_button_block', 'allwork_button');
            }
        }

        return true;
    }

}
