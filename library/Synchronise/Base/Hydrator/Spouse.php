<?php
/**
 * Default hydrator definition for spouses.
 *
 * Don't extend this - instead, stack hydrators on top of it.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Strategy_Hydrator_Spouse implements Synchronise_Base_Strategy_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['username']   = $externalUser['StudentContactSpouseID'];
        $newUser['title']      = $externalUser['StudentContactSpouseTitle'];
        $newUser['firstname']  = $externalUser['StudentContactSpousePreferred'];
        $newUser['lastname']   = $externalUser['StudentContactSpouseSurname'];
        $newUser['synergy_id'] = $externalUser['StudentContactSpouseID'];
        $newUser['alt_email']  = $externalUser['StudentContactSpouseDefaultEmail'];
        $newUser['group_id']   = self::DEFAULT_PARENT_GROUP;
        return $newUser;
    }
}
