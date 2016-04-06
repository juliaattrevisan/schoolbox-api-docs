<?php

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
    public function __construct ($page = null) {
        if ( !is_a($page, 'SitePage') ) {
            $page = new SitePage();
        }

        $this->setPage($page);
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
