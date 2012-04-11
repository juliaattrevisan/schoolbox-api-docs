<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Dashboard_UpcomingWorkController extends Component_Dashboard_ControllerAbstract {

    private $groupHandler;
    private $userHandler;
    private $returnHandler;
    private $submissionHandler;
    private $max;
    private $allWork;

    /**
     * Business logic to collate data ready for output
     */
    public function execute () {
        // initialise handlers
        $this->groupHandler = new Group();
        $this->userHandler = new User();
        $this->returnHandler = new SubmissionReturn();
        $this->submissionHandler = new Submission();
        $this->max = 12; // should there be a limit?

        // determine for which user (or users) we will be retrieving data
        // at the end of this, we want an array of user ids that we will parse
        $userIDs = array();

        // if activeUser is a parent, collate their children into this view
        if($this->getGroupHandler()->isParent()) {
            // for now, we don't want this, but leave it as an option
            return false;
            if (is_array($_SESSION['children'])) {
                foreach ($_SESSION['children'] as $child) {
                    $userIDs[] = $child['id'];
                }
            }
        }
        // elseif activeUser is a student, display current user's data
        elseif ($this->getGroupHandler()->isStudent()) {
            $userIDs[] = $_SESSION['id'];
        }
        // not parent and not student? bug out.
        else {
            return false;
        }

        // setup array to get all work from all users to be displayed
        $allWork = array();

        // set the limit of how many more we can display
        $limit = $this->max;

        // iterate through all students and get their work
        foreach($userIDs as $userID) {
            $user = $this->getUserHandler()->getByID($userID);
            if (!isset($user['uid']))
                error(2);


            $categoryList = $this->getGroupHandler()->involvedInCategoryList($user['uid']);

            $categoryIDList = array();
            if (is_array($categoryList)) {
                foreach($categoryList as $category) {
                    $categoryIDList[] = $category['id'];
                }
            }

            //Catch if not involved in any categories
            if (count($categoryList) == 0)
                return false;

            // get all the upcoming, awaiting and returned work for this student
            $works = array(
                $this->getSubmissionHandler()->getRecentWork($userID, $categoryIDList, UPCOMING_WORK_TYPE),
                //$this->getSubmissionHandler()->getRecentWork($userID, $categoryIDList, AWAITING_WORK_TYPE),
                //$this->getSubmissionHandler()->getRecentWork($userID, $categoryIDList, RETURNED_WORK_TYPE),
            );

            // iterate through this student's work and return all the works up to the limit
            foreach($works as $current) {
                for ($i=0; (is_array($current) && $i < count($current)) && ($i < $limit); $i++) {
                    $current[$i]['owner'] = $user['uid'];
                    $allWork[] = $current[$i];
                }

                /* Recalculate how much can be displayed of the next type, if the current type
                 * doesn't have enough items
                 */
                if ($i < ($limit - 1)) {
                    $limit = ($limit - $i) + $this->max;
                }
            } // end foreach work

        } // end foreach user

        usort($allWork, 'cmp');

        $this->allWork = $allWork;

        return true;
    }

    /**
     * Output the data we collected in exec()
     */
    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'upcomingWork') ) {
            return false;
        }

        $this->setBlock($templateHandle, 'work', 'work_block');

        // get now()
        $current_time = new DateTime();

        // permissions check
        $restrictedSubmissions = false;
        $childList = array();
        if($this->getGroupHandler()->isParent()) {
            // for now, we don't want this, but leave it as an option
            // this will become relevant if due work welcome component is opened to parents
            return false;
            if (is_array($_SESSION['children'])) {
                foreach ($_SESSION['children'] as $child) {
                    $childList[] = $child['id'];
                }
            }
            $restrictedSubmissions = Cfg::get('restrict_student_submissions');
        } else {
            $childList = array($_SESSION['id']);
        }

        // count for dark/light
        $j = 0;

        // for all the work, render output
        if(count($this->allWork) > 0) {
            foreach($this->allWork as $currentWork) {

                // in the case of parents if somehow we got a student who's not our child, skip over it
                if(!in_array($currentWork['owner'], $childList))
                    continue;

                // if relevant date is in the past, we don't want to know about it
                if($currentWork['relevant_date'] < $current_time)
                    continue;

                // if there is no parent restriction in place
                if (!$restrictedSubmissions) {
                    if ($currentWork['status'] == UPCOMING_WORK_TYPE) {
                        if (!$currentWork['accept_submission']) {
                            $status = 'Work due';
                            $action = 'View Details';
                        }
                        else if ($current_time > $currentWork['open_date']) {
                            if ($current_time < $currentWork['date']) {
                                $status = 'Work due';
                                $action = 'Submit Work';
                            }
                            else if ($current_time < $currentWork['late_date']) {
                                $status = 'Work overdue';
                                $action = 'Submit Work';
                            }
                            else {
                                $status = 'Missed due date';
                                $action = 'View Details';
                            }
                        }
                        else {
                            $status = 'Open date approaching';
                            $action = 'View Details';
                        }
                    }
                    else if ($currentWork['status'] == AWAITING_WORK_TYPE) {
                        $action = 'View Details';

                        if ($current_time < $currentWork['date']) {
                            $status = 'Work submitted';
                        }
                        else if ($currentWork['submitted'] < $currentWork['late_date']) {
                            $status = 'Work submitted late';
                        }
                    }
                    else if ($currentWork['status'] == RETURNED_WORK_TYPE) {
                        $action = 'View Details';

                        if (!empty($currentWork['mark'])) {
                            $status = 'Work marked';
                        }
                        else {
                            $status = 'Work returned';
                        }
                    }
                }
                /* Status to display when Restrict Student Submissions is on
              */
                else {
                    $status = '';
                    $action = '';
                }

                // if the relevant_date is set to the start of end of a day, only show the day itself and truncate the time
                $shortFormat = Cfg::get('date_short');
                $timePos = strpos($shortFormat, 'g:i') ? strpos($shortFormat, 'g:i') - 1 : strlen($shortFormat);
                if( $currentWork['relevant_date']->format('H:i:s') == '00:00:00' ||
                    $currentWork['relevant_date']->format('H:i:s') == '23:59:59' )
                {
                    $this->setVar('WORK_DATE', $currentWork['relevant_date']->format(substr($shortFormat, 0, $timePos)));
                } else {
                    $this->setVar('WORK_DATE', $currentWork['relevant_date']->format(Cfg::get('date_short')));
                }



                $this->setVar(array(
                    'WORK_SUBJECT' => scrub::Text($currentWork['categoryName']),
                    'WORK_SUBJECT_ID' => $currentWork['cid'],
                    'WORK_DESCRIPTION' => scrub::Text($currentWork['name']),
                    'WORK_ACTION' => $action,
                    'WORK_STATUS' => $status,
                    'WORK_BOX_ID' => $currentWork['bid'],
                    'WORK_FILE_ID' => $currentWork['fid'],
                    'ROW_COLOR' => !($j++ % 2) ? 'dark' : ''
                ));

                $this->parse('work_block', 'work', true);
            }
        }
        else {
            $this->setVar('work_block','');
        }









        return true;
    }

    /**
     * @return Group
     */
    private function getGroupHandler() {
        return $this->groupHandler;
    }

    /**
     * @return User
     */
    private function getUserHandler() {
        return $this->userHandler;
    }

    /**
     * @return SubmissionReturn
     */
    private function getReturnHandler() {
        return $this->returnHandler;
    }

    /**
     * @return Submission
     */
    private function getSubmissionHandler() {
        return $this->submissionHandler;
    }

    /**
     * @return array|null
     */
    private function getAllWork() {
        return $this->allWork;
    }
}

/**
 * Order by date descending.
 *
 * @param <type> $a
 * @param <type> $b
 * @return <type>
 */
function cmp($a, $b) {
    if ($a['relevant_date'] == $b['relevant_date'])
        return 0;
    else if ($a['relevant_date'] > $b['relevant_date'])
        return 1;
    return -1;
}