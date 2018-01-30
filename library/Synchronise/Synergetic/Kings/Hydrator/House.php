<?php
/**
 * Hydrator for house for Kings users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Kings_Hydrator_House implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
    	$this->external_user_handler = new Synchronise_Synergetic_Kings_KingsUser();
        $newUser['house'] = $this->getHouseFromUser($externalUser);
        $newUser['residential_house_id'] = $this->getResidentialHouseFromUser($externalUser);
        return $newUser;
    }

    /**
     * Gets house according to the following map:
     *
     *
     * @param  array $externalUser
     * @return int
     */
    public function getHouseFromUser($externalUser) {
    	$student = $this->external_user_handler->getStudent($externalUser['StudentID']);

        switch (strtoupper(trim($externalUser['StudentHouse']))) {
            case 'BRI':
                return 1;
            case 'BRO':
                return 2;
            case 'BUR':
                return 3;
            case 'DAL':
                return 4;
            case 'FOR':
                return 5;
            case 'GB':
                return 6;
            case 'HAK':
		return 7;
	case 'KUR':
		return 8;
	case 'MAW':
		return 9;
	case 'MAC':
		return 9;
	case 'MAQ':
		return 10;
	case 'WAD':
		return 11;
	case 'WIC':
		return 12;
	case 'BAH':
		return 13;
	case 'BAK':
		return 13;
	case 'BBH':
		return 14;
	case 'BLA':
		return 15;
	case 'HAS':
		return 16;
	case 'STI':
		return 17;
	case 'THO':
		return 18;
	case 'HAR':
		return 19;
	case 'BRF':
		return 20;
	case 'HHA':
		return 19;
         default:
                return null;
        }

        #return $this->getHouse($student['StudentHouseDescription']);
    }
    
    public function getResidentialHouseFromUser($externalUser) {

    	
	$student = $this->external_user_handler->getStudent($externalUser['StudentID']);
	#echo "in: ".$student['StudentBoardingHouse']."\n";
    	if (empty($student['StudentBoardingHouse'])) {
    		return null;
    	} else {
        switch (strtoupper(trim($student['StudentBoardingHouse']))) {
        case 'FOR':
		return 5;
	case 'GB':
		return 6;
	case 'HHA':
		return 7;
	case 'MAW':
		return 11;
	case 'BAH':
		return 13;
	case 'BBH':
		return 14;
	case 'BRF':
		return 20; 
	case 'BB':
		return 14;
	case 'MAC':
		return 11;
	case 'WAD':
		return 11; 
	default:
                return null;
        }

#    		return $this->getResidentialHouse($student['StudentBoardingHouseDescription']);
    	}
    }
    
    protected function getHouse($house) {
        $house = HouseMapper::getInstance()->findByColumn('house',$house);

        if ($house->count() > 0)
            return $house->first()->getId();
    }

    protected function getResidentialHouse($residentialHouse) {
	echo "in: $residentialHouse\n";
        $residentialHouse = HouseResidentialMapper::getInstance()->findByColumn('name',$residentialHouse);
	echo json_encode($residentialHouse)."\n";
	echo "--end res find\n";
        if ($residentialHouse->count() > 0)
            return $residentialHouse->first()->getId();
    }
}
