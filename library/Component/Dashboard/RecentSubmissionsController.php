<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Dashboard_RecentSubmissionsController extends Component_Dashboard_ControllerAbstract {

    private $groupHandler;
    private $submissionHandler;
    private $max = 10;
    private $submissions;
    private $dump;

    public function execute () {
        // init handlers
        $this->groupHandler = new Group();
        $this->submissionHandler = new Submission();


        // don't try to parse data if we're not a teacher
        if(!$this->getGroupHandler()->isStaff())
            return false;

        // returns appropriately sorted and formatted data for this module
        $this->submissions = $this->getSubmissionHandler()->getTotalSubmissionsByOwner($_SESSION['id']);

        return true;
    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'recentSubmissions') ) {
            return false;
        }


        $this->setBlock($templateHandle, 'work', 'work_block');
        $this->setBlock('work', 'submission', 'submission_block');
        $this->setBlock('work', 'assess', 'assess_block');

        $this->setVar('work_block','');

        // determine how many results to output
        $count = 0;
        $limit = $this->getConfig('size');

        if(is_array($this->submissions)) {
            foreach($this->submissions as $submit) {
                // don't exceed the limit
                if($count >= $limit)
                    break;

                $date = new Alaress_Date_Format($submit['datestamp'], 'Y-m-d H:i');

                $output = array(
                    'WORK_DATE'        => $date->getRelativeTime(),
                    'WORK_SUBJECT'     => Scrub::Text($submit['category_name']),
                    'WORK_SUBJECT_ID'  => $submit['cid'],
                    'WORK_DESCRIPTION' => Scrub::Text($submit['name']),
                    'WORK_COUNT'       => $submit['count'],
                    'WORK_BOX_ID'      => $submit['id'],
                    'ROW_COLOR'        => ++$count%2 ? 'dark' : ''
                );

                $this->setVar($output);

                if($submit['availability'] == 'assess') {
                    $this->parse('assess_block', 'assess', false);
                    $this->setVar('submission_block', '');
                } else {
                    $this->parse('submission_block', 'submission', false);
                    $this->setVar('assess_block', '');

                }

                $this->parse('work_block', 'work', true);
            }

            return true;
        }
        else {
            return false;
        }
    }

    /**
     * @return Group
     */
    public function getGroupHandler() {
        return $this->groupHandler;
    }

    /**
     * @return Submission
     */
    public function getSubmissionHandler() {
        return $this->submissionHandler;
    }
}