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

    private $post;
    private $session;

    /**
     * Constructor
     *
     * @param $file
     */
    public function __construct($file, $post, $session) {
        parent::__construct($file);
        $this->post = $post;
        $this->session = $session;
    }
    
    /**
	 * Wrapper for uploading a calendar file which could be in either format
	 *
	 * @return array
	 */
	public function addCalendar() {
		if (is_file($this->getFile())) {

            $source = null;
		    if ($this->post['source'] != null) {
		        $source = $this->post['source'];

		        //Delete old events
                try {
                    Event\Source\Mapper::getInstance()->deleteEventsFromSource($source);
                } catch (Schoolbox\Mapper\DefinitionException $e) {

                }
            }

            /* Calendar Owner overrideco
             */
            if (isset($this->post['userid']) && is_numeric($this->post['userid'])) {
                $userMapper = new \Schoolbox\UserMapper();
                $user = $userMapper->findById($this->post['userid']);
                $author = $user;
            }
            else {
                $user = $this->session['user'];
                $author = $this->session['user'];
            }

			/* Set up specified campus list
			 * Set up specified group list
			 * Set up specified category list
			 */
			$groupList = array();
			$campusList = array();
			$categoryList = array();

			if (is_array($this->post['userGroup'])) {
				$groupList = $this->post['userGroup'];
			}

			if (is_array($this->post['campusList'])) {
				$campusList = $this->post['campusList'];
			}

			if (is_array($this->post['categoryList'])) {
				$categoryList = $this->post['categoryList'];
			}

			/* Use factory to spawn a builder
			 */
			$factory = new \Calendar\Import\Factory($user->getId(), $author->getId(), $categoryList, $groupList, $campusList, \Schoolbox\Calendar\Event::ACCESS_CAMPUS, $this->getFile(), $source);
			$strategy = $factory->build();
			$strategy->load();

			return $strategy->getOutput();
		}

		return array();
	}
}
