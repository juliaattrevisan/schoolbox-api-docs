<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Dashboard_UpcomingWorkController extends Component_Dashboard_ControllerAbstract {

    private $upcomingWork = array();

    /**
     * Business logic to collate data ready for output
     */
    public function execute() {

        // TODO DOES THIS COMPONENT HAVE ACCESS RESTRICTIONS?
        // assumption: this component is only visible to students
        $groupHandler = new Group();
        if(!$groupHandler->isStudent()) {
            return false;
        }

        $submissionHandler = new Submission();

        $userID = $_SESSION['id'];

        $upcoming = $submissionHandler->getAllUpcomingWorkAndAssessments($userID);

        $now = time();
        $j = 0;

        if(!count($upcoming)) {
            return false;
        }

        foreach($upcoming as $item) {
            unset($output);
            // find out if we're actually showing this item
            if($item['kind'] == 0) {
                // duework
                if($item['submittable']) {
                    if($item['multi_submit']) {
                        // if unsubmitted, hide if LATE date passed
                        if(!$item['item_count'] && $item['late_date'] < $now) {
                            $output['HIDEME'] = 'hideme multi unsubmitted late-passed';
                            $output['upcoming_work_actionable_block'] = 'hideme multi unsubmitted late-passed';
                        }
                        // if submitted, hide if DUE date passed
                        elseif($item['submit_count'] && $item['date'] < $now) {
                            $output['HIDEME'] = 'hideme multi submitted due-passed';
                            $output['upcoming_work_actionable_block'] = 'hideme multi submitted due-passed';
                        }
                    }
                    else {
                        // single submit: hide if completed or DUE date in past
                        if($item['date'] < $now || $item['submit_count']) {
                            $output['HIDEME'] = 'hideme single due-passed-or-submitted';
                            $output['upcoming_work_actionable_block'] = 'hideme single due-passed-or-submitted';
                        }
                    }
                }
                else {
                    // no submissions: hide if DUE date in past
                    if($item['date'] < $now) {
                        $output['HIDEME'] = 'hideme nosubmit due-passed';
                        $output['upcoming_work_actionable_block'] = 'hideme nosubmit due-passed';
                    }
                }
            }
            else {
                // assess
                if($item['max_attempts'] == 0) {
                    // 0 max attempts is unlimited: hide if FINISH in past
                    if($item['date'] < $now) {
                        $output['HIDEME'] = 'hideme unlimited due-passed';
                        $output['upcoming_work_actionable_block'] = 'hideme unlimited due-passed';
                    }
                }
                elseif($item['attempt_count'] >= $item['max_attempts']) {
                    // if limited attempts, hide if limit has been reached
                    $output['HIDEME'] = 'hideme attempts-exhausted';
                    $output['upcoming_work_actionable_block'] = 'hideme attempts-exhausted';
                }
                elseif($item['date'] < $now) {
                    $output['HIDEME'] = 'hideme due-passed';
                    $output['upcoming_work_actionable_block'] = 'hideme due-passed';
                }
            }

            // skip if hideme was populated for some reason
            if(isset($output['HIDEME'])) {
                continue;
            }

            $adr = new Alaress_Date_Relative($item['date'], 'y-m-d');

            // how many days left, 0 <= x <= 10
            $urgency = max(0, min(10, (int)(($adr->getUnixTimestamp() - $now) / 86400)));

            // override the above manipulation of output (we don't need it)
            $output = array(
                'UPCOMING_ID'      => $item['id'],
                'UPCOMING_TITLE'   => $item['title'],
                'UPCOMING_TYPE'    => $item['type'],
                'UPCOMING_DATE'    => $adr->getRelativeTime(),
                'UPCOMING_TIME'    => $adr->getUsingFormat('Hi') == '2359' ? $adr->getDateOnly() : $adr->getShort(),
                'UPCOMING_URGENCY' => $urgency,

                'UPCOMING_SUBJ_NAME' => $item['subj_name'],
                'UPCOMING_SUBJ_ID'   => $item['subj_id'],

                'ON_OFF' => ++$j % 2 ? '' : 'dark'
            );

            // determine actions available
            if($item['kind'] == 0) {
                // duework
                $output['UPCOMING_HREF'] = 'submission/submissionHistory/';
                $output['UPCOMING_BTN_ICON']  = 'modify';

                if($item['submittable']) {
                    if($item['multi_submit'] && $item['submit_count']) {
                        // allow resubmit
                        $output['UPCOMING_BTN_LABEL'] = 'Resubmit';
                    }
                    else {
                        // no resubmit
                        $output['UPCOMING_BTN_LABEL'] = 'Submit';
                    }
                }
                else {
                    $output['upcoming_work_actionable_block'] = 'No submissions';
                }
            }
            else {
                // assess
                $output['UPCOMING_HREF'] = 'assess/intro/';
                $output['UPCOMING_BTN_ICON']  = 'up-arrow';

                if($item['attempt_count']) {
                    $output['UPCOMING_BTN_LABEL'] = 'Resit';
                }
                else {
                    $output['UPCOMING_BTN_LABEL'] = 'Sit';
                }
            }
            $this->upcomingWork[] = $output;
        }

        return count($this->upcomingWork) ? true : false;
    }

    /**
     * Output the data we collected in exec()
     */
    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'upcomingWork') ) {
            return false;
        }

        $this->setBlock($templateHandle, 'upcoming_work', 'upcoming_work_block');
        $this->setBlock('upcoming_work', 'upcoming_work_actionable', 'upcoming_work_actionable_block');

        foreach ($this->upcomingWork as $output) {
            $this->setVar($output);
            if($output['upcoming_work_actionable_block'] != 'No submissions') {
                $this->parse('upcoming_work_actionable_block', 'upcoming_work_actionable');
            }
            $this->parse('upcoming_work_block','upcoming_work', true);
        }

        return true;
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