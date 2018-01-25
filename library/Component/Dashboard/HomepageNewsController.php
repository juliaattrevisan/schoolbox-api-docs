<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Component_Dashboard_HomepageNewsController extends Component_Dashboard_ControllerAbstract
{
    /** @var array $feedList */
    private $feedList;

    /** @var int $limit */
    private $limit = 20;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
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
                        'name' => $f['name'],
                        'action' => $f['action'],
                        'timestamp' => $feedTime->getUnixTimeStamp(),
                    );
                }

                return true;
            }
        }

        return false;
    }

    /**
     * @param string $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        if (!$this->loadTemplate($templateHandle, 'homepageNews')) {
            return false;
        }

        $this->setVar('SUBJ_HAS_GRADE', 'no-grade');

        $alist = new Activity_ListArray($this->feedList);
        $alist->setShowAvatars(false);
        $alist->setShowTrends(false);
        $alist->setTarget($this->getSitePage(), 'ACTIVITY_LIST');

        foreach ($alist as $a) {
            $alist->renderItem($a);
        }

        return true;
    }

    /**
     * Retrieves feed list as populated by execute().
     *
     * @return array
     */
    public function getDataFromExecute()
    {
        return $this->feedList;
    }

}
