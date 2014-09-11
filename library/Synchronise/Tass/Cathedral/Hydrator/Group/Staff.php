<?php
/**
 * Hydrator for group for Cathedral staff.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Cathedral_Hydrator_Group_Staff implements Synchronise_Base_IHydrator
{

    const GROUP_ADMIN   = 1;
    const GROUP_TEACHER = 2;
    const GROUP_ELC     = 16;

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $groupId = null;
        switch ($externalUser['awd_code']) {
            case "TE":
            case "TECAS":
            case "EX":
                $groupId = self::GROUP_TEACHER;
                break;

            default:
                // check paypoint_text
                switch ($externalUser['paypoint_text']) {
                    case 'ELC':
                    case 'OSHC':
                        $groupId = self::GROUP_ELC;
                        break;
                    default:
                        $groupId = self::GROUP_TEACHER;
                        break;
                }
                break;
        }
        $newUser['group_id'] = $groupId;
        return $newUser;
    }
}
