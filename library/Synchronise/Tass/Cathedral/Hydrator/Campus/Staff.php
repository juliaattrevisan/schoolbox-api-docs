<?php
/**
 * Hydrator for campus for Cathedral staff.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Cathedral_Hydrator_Campus_Staff implements Synchronise_Base_IHydrator
{

    const CAMPUS_SENIOR = 1;
    const CAMPUS_MIDDLE = 2;
    const CAMPUS_ELC    = 3;
    const CAMPUS_JUNIOR = 5;
    const CAMPUS_ADMIN  = 8;

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $campusList = array();
        switch (strtoupper($externalUser['paypoint_text'])) {
            case 'ADMIN':
            case 'MAINT':
            case 'RETAI':
            case 'TSHOP':
            case 'VEH':
                $campusList[] = self::CAMPUS_ADMIN;
                break;
            case 'BOARD':
                $campusList[] = self::CAMPUS_SENIOR;
                $campusList[] = self::CAMPUS_JUNIOR;
                break;
            case 'ELC':
            case 'OSHC':
                $campusList[] = self::CAMPUS_ELC;
                break;
            case 'SNR':
            case 'ROW':
                $campusList[] = self::CAMPUS_SENIOR;
                break;
            case 'JNR':
                $campusList[] = self::CAMPUS_JUNIOR;
                break;
            case 'HIRE':
            case 'OUT':
            case '':
            default:
                break;
        }

        if (empty($campusList)) {
            throw new RangeException('Staff member has no campus');
        }
        $newUser['campus']        = $campusList;
        $newUser['defaultCampus'] = $campusList[0];
        return $newUser;
    }
}
