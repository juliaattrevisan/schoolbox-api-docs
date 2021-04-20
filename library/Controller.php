<?php

/**
 * A really old controller class, which wraps around a SitePage.
 *
 * Old event attendance is built on this, as is the boarder leave module.
 */
class Controller
{
    /**
     * @var SitePage $page
     */
    public $page;

    /**
     * Controller constructor.
     *
     * @param SitePage $page
     */
    public function __construct(SitePage $page)
    {
        $this->page = $page;
    }

    /**
     * @param SitePage $page
     */
    public function setPage($page)
    {
        $this->page = $page;
    }

    /**
     * @return SitePage
     */
    public function getPage()
    {
        return $this->page;
    }
}
