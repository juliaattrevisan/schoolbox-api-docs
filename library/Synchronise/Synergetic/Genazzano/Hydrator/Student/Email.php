<?php
/**
 * Hydrator for student email addresses
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Genazzano_Hydrator_Student_Email implements Synchronise_Base_IHydrator {
    /**
     * Hydrates the Email field, rather than the stand occup email field
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['alt_email'] = trim($externalUser['StudentOccupEmail']);
        return $newUser;
    }
}
