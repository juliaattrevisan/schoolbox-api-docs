<?php

/**
 * Bulk_Calendar
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Bulk_Calendar extends Bulk_Abstract
{

    /**
     * @var string|null
     */
    private $source;


    /**
     * @var int|null
     */
    private $owner;


    /**
     * @var array
     */
    private $categoryList;


    /**
     * @var array
     */
    private $groupList;


    /**
     * @var array
     */
    private $campusList;


    /**
     * Constructor
     *
     * @param string      $file
     * @param string|null $source
     * @param int|null    $owner
     * @param array       $categoryList
     * @param array       $groupList
     * @param array       $campusList
     */
    public function __construct($file, $source, $owner, $categoryList, $groupList, $campusList)
    {
        parent::__construct($file);
        $this->source = $source;
        $this->owner = $owner;
        $this->categoryList = $categoryList;
        $this->groupList = $groupList;
        $this->campusList = $campusList;
    }

    /**
     * Wrapper for uploading a calendar file which could be in either format
     *
     * @return array
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function addCalendar()
    {
        if (is_file($this->getFile())) {
            /* Calendar Owner overrideco
             */
            if ($this->owner != null && is_numeric($this->owner)) {
                $userMapper = new \Schoolbox\UserMapper();
                $user = $userMapper->findById($this->owner);
                $author = $user;
            } else {
                $user = $_SESSION['user'];
                $author = $_SESSION['user'];
            }

            if ($this->source != null) {
                //Delete old events
                Event\Source\Mapper::getInstance()->deleteEventsFromSource($this->source);
            }

            /* Use factory to spawn a builder
             */
            $factory = new \Calendar\Import\Factory($user, $author, $this->categoryList, $this->groupList, $this->campusList, \Schoolbox\Calendar\Event::ACCESS_CAMPUS, $this->getFile(), $this->source);
            $strategy = $factory->build();
            $strategy->load();

            return $strategy->getOutput();
        }

        return array();
    }
}
