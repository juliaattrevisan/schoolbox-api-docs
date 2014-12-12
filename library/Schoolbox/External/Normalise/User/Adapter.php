<?php
/**
 * Adapter
 *
 * All the stuff in here should ideally be done in the Connector
 * classes themselves, outputting standardised objects. They don't.
 * They give you arbitrary arrays of data which adheres to copy-paste
 * convention only.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User;
use Schoolbox\Collection;

/* Interface usage disabled while the repopulation takes place
 * because it'll bleat at me until I implement all methods
 */
class Adapter {

    /**
     * Crappy alex handler
     *
     * @var null
     */
    private $handler = null;

    /**
     * Accepts an External_User
     *
     * @param \External_User $handler
     */
    public function __construct(\External_User $handler) {
        $this->setHandler($handler);
    }

    /**
     * Get portrait
     *
     * @param $externalUserId
     * @return String
     */
    public function getPortrait($externalUserId) {
        return $this->getHandler()->getPortrait($externalUserId);
    }

    /**
     * Get parent $externalUserId
     *
     * @param $externalUserId
     * @return Collection
     */
    public function getParent($externalUserId) {
        $currentResult = $this->getHandler()->getParent($externalUserId);

        $guardian1PhoneHome = new Common\Phone();
        $guardian1PhoneHome
            ->setNumber($currentResult['HomePhone'])
            ->setSilent($currentResult['SilentPhoneFlag']);

        $guardian1PhoneMobile = new Common\Phone();
        $guardian1PhoneMobile
            ->setNumber($currentResult['MobilePhone']);

        $guardian1 = new Guardian();
        $guardian1
            ->setTitle($currentResult['Title'])
            ->setPreferredName($currentResult['Preferred'])
            ->setLastname($currentResult['Surname'])
            ->setEmail($currentResult['Email'])
            ->setPhoneHome($guardian1PhoneHome)
            ->setPhoneMobile($guardian1PhoneMobile)
            ->setPubliclyVisible($currentResult['DirectoryIncludeFlag']);

        $guardian2PhoneMobile = new Common\Phone();
        $guardian2PhoneMobile
            ->setNumber($currentResult['SpouseMobilePhone']);

        $guardian2 = new Guardian();
        $guardian2
            ->setTitle($currentResult['SpouseTitle'])
            ->setPreferredName($currentResult['SpousePreferred'])
            ->setLastname($currentResult['SpouseSurname'])
            ->setEmail($currentResult['SpouseEmail'])
            ->setPhoneMobile($guardian2PhoneMobile)
            ->setPubliclyVisible($currentResult['SpouseDirectoryIncludeFlag']);

        $collection = new Collection();
        $collection->add($guardian1);
        $collection->add($guardian2);

        return $collection;
    }

    /**
     * Get parents for a list of $externalUserIds
     *
     * TODO: This is synonymous with an updated version of getParent();
     *
     * @param $externalUserId
     * @return Collection
     */
    public function getParentSync($externalUserId) {
        $collection = new Collection();

        $allResults = $this->getHandler()->getParentSync($externalUserId);

        if (is_array($externalUserId)) {
            foreach ($allResults as $currentResult) {
                $guardian = new Guardian();
                $guardian
                    ->setId($currentResult['ID'])
                    ->setTitle($currentResult['Title'])
                    ->setPreferredName($currentResult['Preferred'])
                    ->setLastname($currentResult['Surname'])
                    ->setEmail($currentResult['Email'])
                    ->setPubliclyVisible($currentResult['DirectoryIncludeFlag']);

                $collection->add($guardian);
            }
        }
        else {
            $guardian = new Guardian();
            $guardian
                ->setId($allResults['ID'])
                ->setTitle($allResults['Title'])
                ->setPreferredName($allResults['Preferred'])
                ->setLastname($allResults['Surname'])
                ->setEmail($allResults['Email'])
                ->setPubliclyVisible($allResults['DirectoryIncludeFlag']);

            $collection->add($guardian);
        }

        return $collection;
    }

    /**
     * Get a list of emails for a bunch of ids
     *
     * TODO: This is synonymous with an updated version of getParent();
     *
     * @param $externalUserId
     * @return Collection
     */
    public function getParentEmails($externalUserId) {
        $collection = new Collection();

        $allResults = $this->getHandler()->getParentEmails($externalUserId);

        foreach ($allResults as $currentResult) {
            $guardian = new Guardian();
        }

        return $collection;
    }

    /**
     * Get a bunch of students
     *
     * @return Collection
     */
    public function getStudentBoarderContactListExtended() {
        $collection = new Collection();

        $allResults = $this->getHandler()->getStudentBoarderContactListExtended();

        foreach($allResults as $currentResult) {

            $guardian1Address = new Common\Address();
            $guardian1Address
                ->setAddress1($currentResult['StudentContactAddress1'])
                ->setAddress2($currentResult['StudentContactAddress2'])
                ->setAddress3($currentResult['StudentContactAddress3'])
                ->setSuburb($currentResult['StudentContactSuburb'])
                ->setState($currentResult['StudentContactState'])
                ->setPostcode($currentResult['StudentContactPostCode'])
                ->setCountry($currentResult['StudentContactCountryDesc']);

            $guardian1PhoneHome = new Common\Phone();
            $guardian1PhoneHome
                ->setNumber($currentResult['StudentContactPhoneActual'])
                ->setSilent($currentResult['StudentContactSilentPhoneFlag']);

            $guardian1PhoneMobile = new Common\Phone();
            $guardian1PhoneMobile
                ->setNumber($currentResult['StudentContactMobilePhone']);

            $guardian1PhoneWork = new Common\Phone();
            $guardian1PhoneWork
                ->setNumber($currentResult['StudentContactOccupPhone']);

            $guardian1 = new Guardian();
            $guardian1
                ->setId($currentResult['StudentContactID'])
                ->setTitle($currentResult['StudentContactTitle'])
                ->setPreferredName($currentResult['StudentContactPreferred'])
                ->setLastname($currentResult['StudentContactSurname'])
                ->setPostalAddress($guardian1Address)
                ->setHomeAddress($guardian1Address)
                ->setEmail($currentResult['StudentContactDefaultEmail'])
                ->setPhoneHome($guardian1PhoneHome)
                ->setPhoneMobile($guardian1PhoneMobile)
                ->setPhoneWork($guardian1PhoneWork)
                ->setPubliclyVisible($currentResult['PrimaryDirectoryIncludeFlag']);

            $guardian2Address = new Common\Address();
            $guardian2Address
                ->setAddress1($currentResult['StudentContactSpouseAddress1'])
                ->setAddress2($currentResult['StudentContactSpouseAddress2'])
                ->setAddress3($currentResult['StudentContactSpouseAddress3'])
                ->setSuburb($currentResult['StudentContactSpouseSuburb'])
                ->setState($currentResult['StudentContactSpouseState'])
                ->setPostcode($currentResult['StudentContactSpousePostCode'])
                ->setCountry($currentResult['StudentContactSpouseCountryDesc']);

            $guardian2PhoneHome = new Common\Phone();
            $guardian2PhoneHome
                ->setNumber($currentResult['StudentContactSpousePhoneActual'])
                ->setSilent($currentResult['StudentContactSpouseSilentPhoneFlag']);

            $guardian2PhoneMobile = new Common\Phone();
            $guardian2PhoneMobile
                ->setNumber($currentResult['StudentContactSpouseMobilePhone']);

            $guardian2PhoneWork = new Common\Phone();
            $guardian2PhoneWork
                ->setNumber($currentResult['StudentContactSpouseOccupPhone']);

            $guardian2 = new Guardian();
            $guardian2
                ->setId($currentResult['StudentContactSpouseID'])
                ->setTitle($currentResult['StudentContactSpouseTitle'])
                ->setPreferredName($currentResult['StudentContactSpousePreferred'])
                ->setLastname($currentResult['StudentContactSpouseSurname'])
                ->setPostalAddress($guardian2Address)
                ->setHomeAddress($guardian2Address)
                ->setEmail($currentResult['StudentContactSpouseDefaultEmail'])
                ->setPhoneHome($guardian2PhoneHome)
                ->setPhoneMobile($guardian2PhoneMobile)
                ->setPhoneWork($guardian2PhoneWork)
                ->setPubliclyVisible($currentResult['SpouseDirectoryIncludeFlag']);

            /* Compile student record with known data
             */
            $student = new Student();
            $student
                ->setId($currentResult['StudentID'])
                ->setGivenName($currentResult['StudentGiven1'])
                ->setPreferredName($currentResult['StudentGiven1'])
                ->setLastname($currentResult['StudentSurname']);

            /* Relations
             */
            $student->getYear()->setLevel($currentResult['StudentYearLevel']);
            $student->getHouse()->setName($currentResult['StudentHouse']);

            /* Parents/Guardians
             */
            $student->getGuardians()->add($guardian1);
            $student->getGuardians()->add($guardian2);

            /* Stack it up
             */
            $collection->add($student);
        }

        return $collection;
    }

    /* Getters/Setters
     */

    /**
     * Set handler
     *
     * @param \External_User $handler
     */
    private function setHandler($handler)
    {
        $this->handler = $handler;
    }

    /**
     * Get handler
     *
     * @return \External_User
     */
    private function getHandler()
    {
        return $this->handler;
    }
}