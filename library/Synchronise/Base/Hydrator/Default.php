<?php
/**
 * Default hydrator definition.
 *
 * Please don't extend this - instead, use
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Strategy_Hydrator_Default implements Synchronise_Base_Strategy_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['username']             = $externalUser['NetworkLogin'];
        $newUser['title']                = '';
        $newUser['firstname']            = '';
        $newUser['lastname']             = '';
        $newUser['synergy_id']           = '';
        $newUser['alt_email']            = null;
        $newUser['local_email']          = 0;
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
//        return $this->clearFields($newUser);
    }


//    /**
//     * Filters fields with a null value out of user details.
//     *
//     * @param  array $new_user
//     *
//     * @return array
//     */
//    protected function clearFields($new_user) {
//        return array_filter($new_user, function($v) { return isset($v); });
//    }
}
