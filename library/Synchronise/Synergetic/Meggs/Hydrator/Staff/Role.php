<?php
/**
 * Hydrator for role for Meggs staff.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Meggs_Hydrator_Staff_Role implements Synchronise_Base_IHydrator
{
    /**
     * Hydrates role based on category type.
     *
     * * ADMIN, COACH, SA: Non-Teaching (1)
     * * CASUAL, MUSI, TCHO: Teaching (2)
     */
    public function hydrate($externalUser, $newUser)
    {
        switch ($externalUser['StaffCategoryType']) {
            case 'ADMIN':
            case 'COACH':
            case 'SA':
                $group = 1;
                break;
            case 'CASUAL':
            case 'MUSI':
            case 'TCHO':
                $group = 2;
                break;
            default:
                throw new Exception(
                    sprintf(
                        'Category type "%s" not recognized',
                        $externalUser['StaffCategoryType']
                    )
                );
        }
        $newUser['group_id'] = $group;
        return $newUser;
    }
}
