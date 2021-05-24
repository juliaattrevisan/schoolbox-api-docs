<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
        $feedHandler = new Feed(DeprecatedDatabase::getInstance(), $this->getUser());
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
                $categoryIDs,
                $this->getConfig('size') ? $this->getConfig('size') : $this->limit
            );

            // map the feed for the ActivityList
            if (count($feedList)) {
                foreach ($feedList as $f) {
                    $feedTime = new Alaress_Date_Format($f['date'], 'U');
                    $this->feedList[] = array(
                        'icon' => $f['type'],
                        'content' => $f['messageHTML'] . (empty($f['cat_name']) ? '' : ' in <a href="/homepage/' . $f['cat_id'] . '">' . Scrub::text($f['cat_name']) . '</a>'),
                        'time' => $feedTime->getRelativeTime(),
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

        $this->setBlock($templateHandle, 'activity_item', 'activity_item_block');

        foreach ($this->feedList as $item) {
            foreach ($item as $key => $value) {
                $this->setVar('ACTLIST_' . strtoupper($key), $value);
            }
            $this->parse('activity_item_block', 'activity_item', true);
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
