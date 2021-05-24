<?php

/**
 * Leave_Report_Catering_Abstract
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Leave_Report_Catering_Abstract
{
    protected $page = null;
    protected $data = array();

    /**
     * Constructor
     *
     * @param SitePage $page
     * @param array $data
     */
    public function __construct($page, $data)
    {
        $this->setPage($page);
        $this->setData($data);
        $this->setup();
    }

    /**
     * Hook method
     */
    protected function setup()
    {
    }

    abstract public function render();

    /* Getters/Setters
     */

    /**
     * Set data
     *
     * @param array $data
     */
    protected function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Get data
     *
     * @return array
     */
    protected function getData()
    {
        return $this->data;
    }

    /**
     * Set page
     *
     * @param SitePage $page
     */
    protected function setPage($page)
    {
        $this->page = $page;
    }

    /**
     * Get page
     *
     * @return SitePage
     */
    protected function getPage()
    {
        return $this->page;
    }
}
