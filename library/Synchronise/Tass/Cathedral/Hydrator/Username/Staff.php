<?php
/**
 * Hydrator for username for Cathedral staff - always comes from emp_code.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Cathedral_Hydrator_Username_Staff implements Synchronise_Base_IHydrator {

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['username'] = $externalUser['emp_code'];
        return $newUser;
    }
}
