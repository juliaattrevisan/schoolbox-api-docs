<?php
/**
 * Hydrator for username for Cathedral staff.
 *
 * For teachers: this comes from tch_code, otherwise from emp_code
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Cathedral_Hydrator_Username_Staff implements Synchronise_Base_IHydrator {

    const GROUP_ADMIN   = 1;
    const GROUP_TEACHER = 2;
    const GROUP_ELC     = 16;

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $username = null;
        switch ($externalUser['awd_code']) {
            case "TE":
            case "TECAS":
            case "EX":
                $username = $externalUser['tch_code'];
                break;

            default:
                $username = $externalUser['emp_code'];
                break;
        }
        $newUser['username'] = $username;
        return $newUser;
    }
}
