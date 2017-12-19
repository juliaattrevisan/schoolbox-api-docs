<?php
/**
 * Bulk_Calendar
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Bulk_Calendar extends Bulk_Abstract {

    private $source;
    private $owner;
    private $categoryList;
    private $groupList;
    private $campusList;


    /**
     * Constructor
     *
     * @param $file
     * @param $source
     * @param $owner
     * @param $categoryList
     * @param $groupList
     * @param $campusList
     */
    public function __construct($file, $source, $owner, $categoryList, $groupList, $campusList) {
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
	 */
	public function addCalendar() {
		if (is_file($this->getFile())) {

		    if ($this->source != null) {
		        //Delete old events
                Event\Source\Mapper::getInstance()->deleteEventsFromSource($this->source);
            }

            /* Calendar Owner overrideco
             */
            if ($this->owner != null && is_numeric($this->owner)) {
                $userMapper = new \Schoolbox\UserMapper();
                $user = $userMapper->findById($this->owner);
                $author = $user;
            }
            else {
                $user = $_SESSION['user'];
                $author = $_SESSION['user'];
            }

			/* Use factory to spawn a builder
			 */
			$factory = new \Calendar\Import\Factory($user->getId(), $author->getId(), $this->categoryList, $this->groupList, $this->campusList, \Schoolbox\Calendar\Event::ACCESS_CAMPUS, $this->getFile(), $this->source);
			$strategy = $factory->build();
			$strategy->load();

			return $strategy->getOutput();
		}

		return array();
	}
}
