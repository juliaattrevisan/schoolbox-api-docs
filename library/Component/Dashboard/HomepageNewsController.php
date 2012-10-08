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
        $userAccess = $groupHandler->involvedInCategoryList($_SESSION['id']);

        if (is_array($userAccess)) {
            $categoryIDs = array();

            foreach ($userAccess as $ua) {
                $categoryIDs[] = $ua['id'];
            }

            $feedList = $feedHandler->getFeed(
                $categoryIDs, $this->getConfig('size') ? $this->getConfig('size') : $this->limit
            );

            // map the feed for the ActivityList
            if (count($feedList)) {
                foreach ($feedList as $f) {
                    $feedTime = new Alaress_Date_Relative($f['date']);
                    $feedTime->setPrefix('');

                    $this->feedList[] = array(
                        'icon' => $f['type'],
                        'content' => $f['messageHTML'] . (empty($f['cat_name']) ? '' : ' in <a href="/homepage/' . $f['cat_id'] . '">' . $f['cat_name'] . '</a>'),
                        'time' => $feedTime->getRelativeTime(),
                        'avatar' => $f['external_id'] != false ? '/portrait.php?userId=' . $f['external_id'] . '&size=square32' : '',
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

        $alist = new Activity_ListArray($this->feedList);
        $alist->setShowAvatars(true);
        $alist->setTarget($this->sitepage, 'ACTIVITY_LIST');
        foreach ($alist as $a) {
            // iterates the set
        }
        return true;
    }

}