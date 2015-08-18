<?php
/**
 * Default hydrator definition.
 *
 * Please don't extend this class - instead, define a new hydrator which
 * overrides the fields that need to be overridden per user, and add it in the
 * sync's setup() method:
 *
 * <code>
 * protected function setup() {
 *     $this
 *         ->addHydrator(new Synchronise_Base_Hydrator_Default())
 *         ->addHydrator(new Synchronise_Base_Hydrator_Student())
 *         ->addHydrator(instanceof Synchronise_Base_IHydrator)
 *     ;
 * }
 * </code>
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_Default implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['username']             = isset($externalUser['NetworkLogin']) ? $externalUser['NetworkLogin'] : '';
        $newUser['title']                = '';
        $newUser['firstname']            = '';
        $newUser['lastname']             = '';
        $newUser['synergy_id']           = '';
        $newUser['alt_email']            = null;
        $newUser['local_email']          = 0;
        $newUser['webmail']              = null;
        $newUser['year']                 = null;
        $newUser['defaultCampus']        = self::DEFAULT_CAMPUS;
        $newUser['campus']               = array(self::DEFAULT_CAMPUS);
        $newUser['group_id']             = self::DEFAULT_ADMIN_GROUP;
        $newUser['su']                   = false;
        $newUser['house']                = 0;
        $newUser['residential_house_id'] = 0;
        $newUser['eportfolio_enabled']   = true;
        $newUser['enabled']              = null;
        return $newUser;
    }
}
