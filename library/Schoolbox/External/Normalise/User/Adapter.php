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

    /* Parents
     */

    /**
     * Get parent $externalUserId
     *
     * @param $externalUserId
     * @return Collection
     */
    public function getParent($externalUserId) {
        $currentResult = $this->getHandler()->getParent($externalUserId);

        /* Assemble first guardian
         */
        $guardian1Email = new Common\Email();
        $guardian1Email->setAddress($currentResult['Email']);

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
            ->addEmail($guardian1Email)
            ->setPhoneHome($guardian1PhoneHome)
            ->setPhoneMobile($guardian1PhoneMobile)
            ->setPubliclyVisible($currentResult['DirectoryIncludeFlag']);

        /* Assemble second guardian
         */
        $guardian2Email = new Common\Email();
        $guardian2Email->setAddress($currentResult['SpouseEmail']);

        $guardian2PhoneMobile = new Common\Phone();
        $guardian2PhoneMobile
            ->setNumber($currentResult['SpouseMobilePhone']);

        $guardian2 = new Guardian();
        $guardian2
            ->setTitle($currentResult['SpouseTitle'])
            ->setPreferredName($currentResult['SpousePreferred'])
            ->setLastname($currentResult['SpouseSurname'])
            ->addEmail($guardian2Email)
            ->setPhoneMobile($guardian2PhoneMobile)
            ->setPubliclyVisible($currentResult['SpouseDirectoryIncludeFlag']);

        /* Stack them up
         */
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
                $guardianEmail = new Common\Email();
                $guardianEmail->setAddress($currentResult['Email']);

                $guardian = new Guardian();
                $guardian
                    ->setId($currentResult['ID'])
                    ->setTitle($currentResult['Title'])
                    ->setPreferredName($currentResult['Preferred'])
                    ->setLastname($currentResult['Surname'])
                    ->addEmail($guardianEmail)
                    ->setPubliclyVisible($currentResult['DirectoryIncludeFlag']);

                $collection->add($guardian);
            }
        }
        else {
            $guardianEmail = new Common\Email();
            $guardianEmail->setAddress($allResults['Email']);

            $guardian = new Guardian();
            $guardian
                ->setId($allResults['ID'])
                ->setTitle($allResults['Title'])
                ->setPreferredName($allResults['Preferred'])
                ->setLastname($allResults['Surname'])
                ->addEmail($guardianEmail)
                ->setPubliclyVisible($allResults['DirectoryIncludeFlag']);

            $collection->add($guardian);
        }

        return $collection;
    }

    /**
     * Get a list of emails of parents for a bunch of student $externalUserIds
     *
     * TODO: This is synonymous with an updated version of getParent();
     *
     * @param array $externalUserId
     * @return Collection
     */
    public function getParentEmails($externalUserId) {
        $allGuardians = array();

        $allResults = $this->getHandler()->getParentEmails($externalUserId);

        /* Samuel made array keys in the format 131-0, 131-1
         * to allow multiple email addresses from a connector
         */
        $allKeys = array_keys($allResults);

        foreach ($allKeys as $currentKey) {

            /* bits equals boom
             */
            $bits = explode('-', $currentKey);
            $id = $bits[0];

            /* Get the email address
             */
            $guardianEmail = new Common\Email();
            $guardianEmail->setAddress($allResults[$currentKey]);

            /* Check if the guardian already exists
             * Otherwise spawn a new one
             */
            if ($allGuardians[$id] instanceof Guardian) {
                $allGuardians[$id]->addEmail($guardianEmail);
            }
            else {
                $guardian = new Guardian();
                $guardian
                    ->setId($id)
                    ->addEmail($guardianEmail);

                $allGuardians[$id] = $guardian;
            }
        }

        /* Stack 'em up
         */
        $collection = new Collection(array_values($allGuardians));

        return $collection;
    }

    /* Students
     */

    /**
     * Get a bunch of students
     *
     * @return Collection
     */
    public function getStudentBoarderContactListExtended() {
        $collection = new Collection();

        $allResults = $this->getHandler()->getStudentBoarderContactListExtended();

        foreach($allResults as $currentResult) {

            /* Guardian 1
             */
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

            $guardian1Email = new Common\Email();
            $guardian1Email->setAddress($currentResult['StudentContactDefaultEmail']);

            $guardian1 = new Guardian();
            $guardian1
                ->setId($currentResult['StudentContactID'])
                ->setTitle($currentResult['StudentContactTitle'])
                ->setPreferredName($currentResult['StudentContactPreferred'])
                ->setLastname($currentResult['StudentContactSurname'])
                ->setPostalAddress($guardian1Address)
                ->setHomeAddress($guardian1Address)
                ->addEmail($guardian1Email)
                ->setPhoneHome($guardian1PhoneHome)
                ->setPhoneMobile($guardian1PhoneMobile)
                ->setPhoneWork($guardian1PhoneWork)
                ->setPubliclyVisible($currentResult['PrimaryDirectoryIncludeFlag']);

            /* Guardian 2
             */
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

            $guardian2Email = new Common\Email();
            $guardian2Email->setAddress($currentResult['StudentContactSpouseDefaultEmail']);

            $guardian2 = new Guardian();
            $guardian2
                ->setId($currentResult['StudentContactSpouseID'])
                ->setTitle($currentResult['StudentContactSpouseTitle'])
                ->setPreferredName($currentResult['StudentContactSpousePreferred'])
                ->setLastname($currentResult['StudentContactSpouseSurname'])
                ->setPostalAddress($guardian2Address)
                ->setHomeAddress($guardian2Address)
                ->addEmail($guardian2Email)
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
            $student->getGuardians()
                ->add($guardian1)
                ->add($guardian2)
            ;

            /* Stack it up
             */
            $collection->add($student);
        }

        return $collection;
    }

    /**
     * Get students
     *
     * @param $externalUserId
     * @return Student
     */
    public function getStudent($externalUserId) {
        $currentResult = $this->getHandler()->getStudent($externalUserId);

        /* Guardian 1
         */
        $guardian1Phone1 = new Common\Phone();
        $guardian1Phone1
            ->setNumber($currentResult['StudentEmergencyPhone']);

        $guardian1Phone2 = new Common\Phone();
        $guardian1Phone2
            ->setNumber($currentResult['StudentEmergencyPhoneAlt']);

        $guardian1 = new Guardian();
        $guardian1
            ->setPreferredName($currentResult['StudentEmergencyName'])
            ->setPhoneHome($guardian1Phone1)
            ->setPhoneWork($guardian1Phone2)
        ;

        /* Guardian 2
         */
        $guardian2Phone1 = new Common\Phone();
        $guardian2Phone1
            ->setNumber($currentResult['StudentEmergency2Phone']);

        $guardian2Phone2 = new Common\Phone();
        $guardian2Phone2
            ->setNumber($currentResult['StudentEmergency2PhoneAlt']);

        $guardian2 = new Guardian();
        $guardian2
            ->setPreferredName($currentResult['StudentEmergency2Name'])
            ->setPhoneHome($guardian2Phone1)
            ->setPhoneWork($guardian2Phone2)
        ;

        $guardian3Phone1 = new Common\Phone();
        $guardian3Phone1
            ->setNumber($currentResult['StudentEmergency3Phone']);

        $guardian3Phone2 = new Common\Phone();
        $guardian3Phone2
            ->setNumber($currentResult['StudentEmergency3PhoneAlt']);

        $guardian3 = new Guardian();
        $guardian3
            ->setPreferredName($currentResult['StudentEmergency3Name'])
            ->setPhoneHome($guardian3Phone1)
            ->setPhoneWork($guardian3Phone2)
        ;

        $student = new Student();
        $student
            ->setId($currentResult['StudentID'])
            ->setGivenName($currentResult['StudentGiven1'])
            ->setLastname($currentResult['StudentSurname'])
            ->setDob($currentResult['StudentBirthDate'])
            ->setLocker($currentResult['StudentLockerBook'])
            ->setMedical($currentResult['StudentMedicalAlert'])
            ->setSpecialNeeds($currentResult['StudentSpecialNeedsFlag'])
            ->setAlert($currentResult['AlertMessage'])
        ;

        $student
            ->getGuardians()
                ->add($guardian1)
                ->add($guardian2)
                ->add($guardian3)
        ;

        $student
            ->getForm()
                ->setLevel($currentResult['StudentForm'])
                ->setDescription($currentResult['FormDescription'])
        ;

        $student
            ->getForm()
                ->getCoordinator()
                    ->setId($currentResult['TeacherFormID'])
                    ->setPreferredName($currentResult['StudentFormStaffName'])
        ;

        $student
            ->getYear()
            ->setLevel($currentResult['StudentYearLevel'])
            ->getCoordinator()
            ->setId($currentResult['YearLevelCoordID'])
            ->setPreferredName($currentResult['StudentYearLevelCoordinator']);
        ;

        $student
            ->getTutor()
                ->setId($currentResult['TeacherTutorID'])
                ->setPreferredName($currentResult['StudentTutorName'])
                ->setGroup($currentResult['StudentTutorDescription'])
        ;

        $student
            ->getHouse()
                ->setId($currentResult['StudentHouse'])
                ->setName($currentResult['StudentHouseDescription'])
                ->setBoarder($currentResult['StudentBoarder'])
                ->getHousemaster()
                    ->setId($currentResult['HeadOfHouseID'])
                    ->setPreferredName($currentResult['StudentHeadOfHouse'])
        ;

        return $student;
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