<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Activity_ListAbstract implements Iterator {

    const DEFAULT_TARGET_HANDLE = 'ACTIVITY_LIST';
    const TEMPLATE_FILE = 'activity/list.html';

    protected $j = 0;

    /**
     * Will be the datatype of whichever implementation is being used
     * @var Iterator
     */
    protected $datasource;

    protected $showIcons = true;
    protected $showAvatars = true;
    protected $showTimes = true;
    protected $showTrends = true;

    /* Show the grade someone got
     */
    protected $showGrade = false;
    protected $asHTML = true;

    /**
     * @var SitePage
     */
    protected $sitepage;

    /**
     * @var string
     */
    protected $handle;

    /**
     * @var string
     */
    protected $templateFile = self::TEMPLATE_FILE;

    /**
     * @param $datasource
     * @param array $config
     */
    public function __construct(array $config = array()) {
        // map the config array onto existing members
        if(count($config)) {
            foreach($config as $k => $v) {
                if(isset($this->$k)) {
                    $this->$k = $v;
                }
            }
        }
    }

    /**
     * Set a specific sitepage handler
     * @param SitePage $page
     * @param $targetHandle
     */
    public function setTarget(SitePage $page, $targetHandle) {
        $this->sitepage = $page;
        $this->handle = $targetHandle;
        $this->sitepage->setVar($targetHandle, '');
    }

    public function setTemplateFile($templateFile) {
        $this->templateFile = $templateFile;
    }

    /**
     * @param $data
     * @return SitePage
     */
    protected function renderItem($data) {
        // instantiate a new sitepage if the monolith wasn't provided
        if(!$this->sitepage) {
            $this->noInstance = true;
            $this->sitepage = new SitePage(false);
            $this->handle = self::DEFAULT_TARGET_HANDLE;
        }
        $page = $this->sitepage;

        $page->setFile('activityTpl', $this->templateFile);

        $page->setVar('DARK', ++$this->j % 2 ? '' : 'dark');

        $blocks = array('icon', 'avatar', 'time', 'trend', 'grade');

        foreach($blocks as $blk) {
            $page->setBlock('activityTpl', $blk, $blk.'_block');
        }

        foreach($data as $key => $value) {
            // printf("%s =&gt; %s<br/>", 'ACTLIST_' . strtoupper($key), $value);
            $page->setVar('ACTLIST_' . strtoupper($key), $value);
        }

        foreach($blocks as $blk) {
            $member = 'show' . ucfirst($blk) . 's';
            if($this->$member && $data[$blk]) {
                $page->parse($blk.'_block', $blk, false);
            }
            else {
                $page->setVar($blk.'_block', '');
            }
        }

        if(isset($this->noInstance)) {
            // return the result rather than just plaster it into the SitePage
            return $page->template->parse($this->handle, 'activityTpl', true);
        }

        $page->parse($this->handle, 'activityTpl', true);
        return $page;
    }


    /**
     * @return bool
     */
    public function willShowIcons() {
        return $this->showIcons ? true : false;
    }

    /**
     * @return bool
     */
    public function willShowTimes() {
        return $this->showTimes ? true : false;
    }

    /**
     * @return bool
     */
    public function willShowAvatars() {
        return $this->showAvatars ? true : false;
    }

    public function willShowTrends() {
        return $this->showTrends ? true : false;
    }

    public function willShowGrade() {
        return $this->showGrade ? true : false;
    }

    /**
     * @return bool
     */
    public function isHTML() {
        return $this->asHTML ? true : false;
    }

    /**
     * @param $bool bool
     */
    public function setShowIcons($bool) {
        if(is_bool($bool)) {
            $this->showIcons = $bool;
        }
    }

    /**
     * @param $bool bool
     */
    public function setShowTimes($bool) {
        if(is_bool($bool)) {
            $this->showTimes = $bool;
        }
    }

    /**
     * @param $bool bool
     */
    public function setShowAvatars($bool) {
        if(is_bool($bool)) {
            $this->showAvatars = $bool;
        }
    }

    public function setShowTrends($bool) {
        if(is_bool($bool)) {
            $this->showTrends = $bool;
        }
    }

    /**
     * @param $bool bool
     */
    public function setAsHTML($bool) {
        if(is_bool($bool)) {
            $this->asHTML = $bool;
        }
    }

    /**
     * Set show grade
     *
     * @param $bool
     */
    public function setShowGrade($bool) {
        if(is_bool($bool)) {
            $this->showGrade = $bool;
        }
    }
}
