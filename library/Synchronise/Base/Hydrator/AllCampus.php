<?php
/**
 * Hydrator for sync scripts: sets this user as involved in all campuses, and
 * sets their default as whatever the first one is.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_AllCampus implements Synchronise_Base_IHydrator {

    /**
     * List of all campus IDs.
     *
     * @var array
     */
    protected $campusList = array();

    /**
     * Constructor: loads campus list.
     */
    public function __construct() {
        foreach (\Schoolbox\CampusMapper::getInstance()->findAll() as $campus) {
            /** @var \Schoolbox\Campus $campus */
            $this->campusList[] = $campus->getId();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['campus']        = $this->campusList;
        $newUser['defaultCampus'] = $this->campusList[0];
        return $newUser;
    }
}
