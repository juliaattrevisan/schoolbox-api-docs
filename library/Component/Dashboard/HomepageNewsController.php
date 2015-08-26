<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Dashboard_HomepageNewsController extends Component_Dashboard_ControllerAbstract {

    private $feedList;
    private $limit = 20;

    public function execute() {
        $feedHandler = new Feed();
        $groupHandler = new Group();
        $userAccess = $groupHandler->involvedInCategoryList(
            $this->getConfig('user') ? $this->getConfig('user') : $_SESSION['id']
        );

        if (is_array($userAccess)) {
            $categoryIDs = array();

            foreach ($userAccess as $ua) {
                $categoryIDs[] = $ua['id'];
            }
            if (empty($categoryIDs)) {
                return false;
            }

            $feedList = $feedHandler->getFeed(
                $categoryIDs, $this->getConfig('size') ? $this->getConfig('size') : $this->limit
            );

            // map the feed for the ActivityList
            if (count($feedList)) {
                foreach ($feedList as $f) {
                    $feedTime = new Alaress_Date_Format($f['date'], 'U');
                    $this->feedList[] = array(
                        'icon' => $f['type'],
                        'content' => $f['messageHTML'] . (empty($f['cat_name']) ? '' : ' in <a href="/homepage/' . $f['cat_id'] . '">' . $f['cat_name'] . '</a>'),
                        'time' => $feedTime->getRelativeTime(),
                        'avatar' => $f['external_id'] != false ? '/portrait.php?userId=' . $f['external_id'] . '&size=square64' : '',
                        'avatar_title' => $f['user_name'] != false ? $f['user_name'] : '',
                        'has_grade' => 'no-grade',
                        'gradeiant' => '',
                        'grade' => '',
                        'cat_id' => $f['cat_id'],
                        'cat_name' => $f['cat_name'],
                        'external_id' => $f['external_id'],
                        'link' => $f['link'],
                        'user_id' => $f['user_id'],
                        'name'    => $f['name'],
                        'action'  => $f['action'],
                        'timestamp' => $feedTime->getUnixTimestamp(),
                    );
                }
                return true;
            }
        }
        return false;
    }

    public function renderTemplate($templateHandle) {
        if (!$this->loadTemplate($templateHandle, 'homepageNews')) {
            return false;
        }
        $this->setVar('SUBJ_HAS_GRADE', 'no-grade');

        $alist = new Activity_ListArray($this->feedList);
        $alist->setShowAvatars(true);
        $alist->setShowTrends(false);
        $alist->setTarget($this->sitepage, 'ACTIVITY_LIST');
        foreach ($alist as $a) {
            // iterates the set
        }
        return true;
    }

    /**
     * Retrieves feed list as populated by execute().
     *
     * @return array
     */
    public function getDataFromExecute() {
        return $this->feedList;
    }

}