<?php
/**
 * Adapter
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User;
use Schoolbox\Collection;

/* Interface usage disabled while the repopulation takes place
 * because it'll bleat at me until I implement all methods
 */
class Adapter {

    /**
     * Crappy alex handler
     *
     * @var null
     */
    private $handler = null;

    /**
     * Accepts an External_User
     *
     * @param $handler
     */
    public function __construct(\External_User $handler) {
        $this->setHandler($handler);
    }

    /**
     * Get a bunch of students
     *
     * @return \Schoolbox\Collection
     */
    public function getStudentBoarderContactListExtended() {
        $collection = new Collection();

        $allResults = $this->getHandler()->getStudentBoarderContactListExtended();

        foreach($allResults as $currentResult) {
            new \Alaress_Probe($currentResult);

            // TODO: Populate nice objects with crappy array data
        }

        return $collection;
    }

    /* Getters/Setters
     */

    /**
     * Set handler
     *
     * @param null $handler
     */
    private function setHandler($handler)
    {
        $this->handler = $handler;
    }

    /**
     * Get handler
     *
     * @return null
     */
    private function getHandler()
    {
        return $this->handler;
    }
}