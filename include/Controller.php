<?php

class Controller
{
    public $page;

    public function __construct ($page = null) {
        if ( !is_a($page, 'Page') ) {
            $page = new SitePage();
        }

        $this->setPage($page);
    }

    /**
     * @param Page $page
     */
    public function setPage($page)
    {
        $this->page = $page;
    }

    /**
     * @return Page
     */
    public function getPage()
    {
        return $this->page;
    }
}
