<?php

/**
 * Report_Abstract
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Report_Abstract
{

    /**
     * @var null
     */
    protected $page;


    /**
     * @var null
     */
    protected $yearLevel;


    /**
     * @var null
     */
    protected $groupHandler;


    /**
     * @var null
     */
    protected $externalUserHandler;


    /**
     * @var null
     */
    protected $userHandler;


    /**
     * @var array
     */
    protected $allRecords = [];


    /**
     * Set up blocks and stuff
     *
     * @abstract
     */
    abstract protected function setup();


    /**
     * Check permissions. By default only parents and superusers can view this page
     *
     * @return bool
     */
    protected function checkPermissions(): bool
    {
        return $this->getGroupHandler()->isParent() || $this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff();
    }


    /**
     * Render dat
     *
     * @abstract
     */
    abstract public function render(): void;


    /* Getters/Setters
     */

    /**
     * Set year level
     *
     * @param string $yearLevel
     */
    protected function setYearLevel($yearLevel)
    {
        $this->yearLevel = $yearLevel;
    }

    /**
     * Get year level
     *
     * @return string
     */
    public function getYearLevel()
    {
        return $this->yearLevel;
    }

    /**
     * Set group handler
     *
     * @param Group $groupHandler
     */
    protected function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * Get group handler
     *
     * @return Group
     */
    public function getGroupHandler()
    {
        return $this->groupHandler;
    }

    /**
     * Set external user handler
     *
     * @param External_User $externalUserHandler
     */
    protected function setExternalUserHandler($externalUserHandler)
    {
        $this->externalUserHandler = $externalUserHandler;
    }

    /**
     * @param User $userHandler
     */
    protected function setUserHandler($userHandler)
    {
        $this->userHandler = $userHandler;
    }

    /**
     * @return User
     */
    protected function getUserHandler()
    {
        return $this->userHandler;
    }

    /**
     * Get external user handler
     *
     * @return External_UserInterface
     */
    protected function getExternalUserHandler()
    {
        return $this->externalUserHandler;
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

    /**
     * Set all records
     *
     * @param array $allRecords
     */
    protected function setAllRecords($allRecords)
    {
        $this->allRecords = $allRecords;
    }

    /**
     * Get all records
     *
     * @return array
     */
    protected function getAllRecords()
    {
        return $this->allRecords;
    }
}
