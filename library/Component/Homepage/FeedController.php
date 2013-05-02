<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_FeedController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Activity_ListArray
     */
    private $activityList;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

        $feedHandler = new Feed;
        $feedList = $feedHandler->getFeed($this->getContext('id'), $this->getConfig('size'));

        $feed = array();

        if (count($feedList)) {
            foreach ($feedList as $f) {
                $feedTime = new Alaress_Date_Relative($f['date']);
                $feedTime->setPrefix('');
                $feed[] = array(
                    'icon' => $f['type'],
                    'content' => $f['messageHTML'],
                    'time' => $feedTime->getRelativeTime(),
                    'avatar' => $f['external_id'] != false ? '/portrait.php?userId=' . $f['external_id'] . '&size=square32' : '',
                    'avatar_title' => $f['user_name'] != false ? $f['user_name'] : '',
                    'has_grade' => 'no-grade',
                    'gradeiant' => '',
                    'grade' => '',
                );
            }
        }

        $this->activityList = new Activity_ListArray($feed);
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if (!parent::loadTemplate($templateHandle, 'feed')) {
            return false;
        }
        $this->parseConfig();

	    $this->setVar('USER_TOKEN', $_SESSION['key']);

        $this->activityList->setShowAvatars(true);
        $this->activityList->setTarget($this->sitepage, 'ACTIVITY_LIST');
        foreach ($this->activityList as $a) {
            // iterates the set
        }

        return true;
    }

}
