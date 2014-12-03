<?php
/**
 * Default hydrator definition for parents.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_Parent implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        // Handling spouses? have a Spouse hydrator as well
        $newUser['username']   = $externalUser['StudentContactID'];
        $newUser['title']      = $externalUser['StudentContactTitle'];
        $newUser['firstname']  = $externalUser['StudentContactPreferred'];
        $newUser['lastname']   = $externalUser['StudentContactSurname'];
        $newUser['synergy_id'] = $externalUser['StudentContactID'];
        $newUser['alt_email']  = $externalUser['StudentContactDefaultEmail'];
        $newUser['group_id']   = self::DEFAULT_PARENT_GROUP;

        if (empty($newUser['username'])) {
            throw new Exception("Parent ContactID not defined");
        }

        return $newUser;
    }
}
