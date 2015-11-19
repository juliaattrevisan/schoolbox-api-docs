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
use External_StudentAccountIterator;
use Schoolbox;
use Schoolbox\Collection;

/* Interface usage disabled while the repopulation takes place
 * because it'll bleat at me until I implement all methods
 */
class Adapter  implements \External_UserInterface {

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
        $collection
            ->add($guardian1)
            ->add($guardian2)
        ;

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
            if (is_array($allResults) && count($allResults)) {
                foreach ($allResults as $currentResult) {
                    $guardianEmail = new Common\Email();
                    $guardianEmail
                        ->setAddress($currentResult['Email']);

                    $guardian = new Guardian();
                    $guardian
                        ->setId($currentResult['ID'])
                        ->setTitle($currentResult['Title'])
                        ->setPreferredName($currentResult['Preferred'])
                        ->setLastname($currentResult['Surname'])
                        ->addEmail($guardianEmail)
                        ->setPubliclyVisible($currentResult['DirectoryIncludeFlag'])
                    ;

                    $collection->add($guardian);
                }
            }
        }
        else {
            $guardianEmail = new Common\Email();
            $guardianEmail
                ->setAddress($allResults['Email']);

            $guardian = new Guardian();
            $guardian
                ->setId($allResults['ID'])
                ->setTitle($allResults['Title'])
                ->setPreferredName($allResults['Preferred'])
                ->setLastname($allResults['Surname'])
                ->addEmail($guardianEmail)
                ->setPubliclyVisible($allResults['DirectoryIncludeFlag'])
            ;

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

        if (is_array($allResults) && count($allResults)) {

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
        }

        /* Stack 'em up
         */
        $collection = new Collection(array_values($allGuardians));

        return $collection;
    }

    /**
     * Gets all Parents' account info from external db
     *
     * @return Collection
     */
    public function getParentUserAccountInfo() {
        $allGuardians = array();

        $allResults = $this->getHandler()->getParentUserAccountInfo();

        if (is_array($allResults) && count($allResults)) {
            foreach ($allResults as $currentResult) {

                /* Year (common to all Guardians)
                 */
                $student = new Student();
                $student
                    ->getYear()
                    ->setLevel($currentResult['StudentYearLevel'])
                ;

                /* Guardian 1
                 */
                $guardian1 = new Guardian();
                $guardian1
                    ->setId($currentResult['StudentContactID'])
                    ->setTitle($currentResult['StudentContactTitle'])
                    ->setPreferredName($currentResult['StudentContactPreferred'])
                    ->setLastname($currentResult['StudentContactSurname'])
                    ->setGender($currentResult['StudentGender'])
                ;

                /* Email
                 */
                $email = new Common\Email();
                $email
                    ->setAddress($currentResult['StudentContactDefaultEmail'])
                ;

                $guardian1
                    ->addEmail($email);

                /* Account
                 */
                $guardian1
                    ->getAccount()
                        ->setLogin($currentResult['StudentContactNetworkLogin'])
                ;

                /* Campus
                 */
                $campus = new Common\Campus();
                $campus
                    ->setName($currentResult['StudentCampus'])
                ;

                $guardian1
                    ->getCampus()
                        ->add($campus)
                ;

                /* Guardian 2
                 */
                $guardian2 = new Guardian();
                $guardian2
                    ->setId($currentResult['StudentContactSpouseID'])
                    ->setTitle($currentResult['StudentContactSpouseTitle'])
                    ->setPreferredName($currentResult['StudentContactSpousePreferred'])
                    ->setLastname($currentResult['StudentContactSpouseSurname'])
                ;

                /* Email
                 */
                $email = new Common\Email();
                $email
                    ->setAddress($currentResult['StudentContactSpouseDefaultEmail'])
                ;

                $guardian2
                    ->addEmail($email);

                /* Account
                 */
                $guardian2
                    ->getAccount()
                    ->setLogin($currentResult['StudentContactSpouseNetworkLogin'])
                ;

                /* Campus
                 */
                $campus = new Common\Campus();
                $campus
                    ->setName($currentResult['StudentCampus'])
                ;

                $guardian2
                    ->getCampus()
                    ->add($campus)
                ;

                /* Stack it up
                 */
                foreach (array($guardian1, $guardian2) as $currentGuardian) {
                    if (strlen($currentGuardian->getPreferredName())) {

                        /* Add guardian to the registry
                         */
                        if (!isset($allGuardians[$currentGuardian->getId()])) {
                            $allGuardians[$currentGuardian->getId()] = $currentGuardian;
                        }
                        elseif (!($allGuardians[$currentGuardian->getId()] instanceof Guardian)) {
                            $allGuardians[$currentGuardian->getId()] = $currentGuardian;
                        }

                        /* Add student
                         * Temporarily disabled for memory conservation reasons
                         * Re-enable in second stage
                         */
//                        $allGuardians[$currentGuardian->getId()]
//                            ->getStudents()
//                                ->add($student)
//                        ;
                    }
                }
            }
        }

        /* Reindex collection
         */
        $collection = new Collection($allGuardians);
        $collection->reindex();

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function getParentUserAccountIterator()
    {
        return $this->getHandler()->getParentUserAccountIterator();
    }

    /* Students
     */

    /**
     * Get students
     *
     * TODO: This is a variation of getStudentContactListExtended()
     *
     * @param $externalUserId
     * @return Student
     */
    public function getStudent($externalUserId) {
        $currentResult = $this->getHandler()->getStudent($externalUserId);

        /* Guardian 1
         */
        $guardian1 = new Guardian();
        $guardian1
            ->setPreferredName($currentResult['StudentEmergencyName'])
        ;

        $guardian1
            ->getPhoneHome()
                ->setNumber($currentResult['StudentEmergencyPhone'])
        ;

        $guardian1
            ->getPhoneWork()
                ->setNumber($currentResult['StudentEmergencyPhoneAlt'])
        ;

        /* Guardian 2
         */
        $guardian2 = new Guardian();
        $guardian2
            ->setPreferredName($currentResult['StudentEmergency2Name'])
        ;

        $guardian2
            ->getPhoneHome()
                ->setNumber($currentResult['StudentEmergency2Phone'])
        ;

        $guardian2
            ->getPhoneWork()
                ->setNumber($currentResult['StudentEmergency2PhoneAlt'])
        ;

        /* Guardian 3
         */
        $guardian3 = new Guardian();
        $guardian3
            ->setPreferredName($currentResult['StudentEmergency3Name'])
        ;

        $guardian3
            ->getPhoneHome()
                ->setNumber($currentResult['StudentEmergency3Phone'])
        ;

        $guardian3
            ->getPhoneWork()
                ->setNumber($currentResult['StudentEmergency3PhoneAlt'])
        ;

        /* Combine it
         */
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

        /* Add guardians only if they exist (ie. have a valid name)
         */
        foreach (array($guardian1, $guardian2, $guardian3) as $currentGuardian) {
            if (strlen($currentGuardian->getPreferredName())) {
                $student->getGuardians()->add($currentGuardian);
            }
        }

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
                    ->setPreferredName($currentResult['StudentYearLevelCoordinator'])
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
                ->getHousemaster()
                    ->setId($currentResult['HeadOfHouseID'])
                    ->setPreferredName($currentResult['StudentHeadOfHouse'])
        ;

        // TODO: Refactor into adaptation layer

        if ($currentResult['StudentBoarder'] == 'Y') {
            $boardingHouse = new Common\BoardingHouse();
            $student->setBoardingHouse($boardingHouse);
        }

        return $student;
    }

    /**
     * Get a list of student contacts (parents) and their contact information
     *
     * TODO: This is a variation of getStudentContactListExtended()
     *
     * @param $externalUserId
     *
     * @return Collection
     */
    public function getStudentContactList($externalUserId) {
        $collection = new Collection();

        $allResults = $this->getHandler()->getStudentContactList($externalUserId);

        if (is_array($allResults) && count($allResults)) {
            foreach ($allResults as $currentResult) {

                /* Guardian 1
                 */
                $guardian1Email = new Common\Email();
                $guardian1Email
                    ->setAddress($currentResult['StudentContactDefaultEmail'])
                ;

                $guardian1 = new Guardian();
                $guardian1
                    ->setId($currentResult['StudentContactID'])
                    ->setTitle($currentResult['StudentContactTitle'])
                    ->setPreferredName($currentResult['StudentContactPreferred'])
                    ->setLastname($currentResult['StudentContactSurname'])
                    ->addEmail($guardian1Email)
                    ->setPubliclyVisible($currentResult['PrimaryDirectoryIncludeFlag'])
                    ->setType($currentResult['StudentContactType'])
                    ->setRole($currentResult['StudentContactRelation'])
                ;

                $guardian1
                    ->getPostalAddress()
                    ->setAddress1($currentResult['StudentContactAddress1'])
                    ->setAddress2($currentResult['StudentContactAddress2'])
                    ->setAddress3($currentResult['StudentContactAddress3'])
                    ->setSuburb($currentResult['StudentContactSuburb'])
                    ->setState($currentResult['StudentContactState'])
                    ->setPostcode($currentResult['StudentContactPostCode'])
                ;

                $guardian1
                    ->getHomeAddress()
                    ->setAddress1($currentResult['StudentContactAddress1'])
                    ->setAddress2($currentResult['StudentContactAddress2'])
                    ->setAddress3($currentResult['StudentContactAddress3'])
                    ->setSuburb($currentResult['StudentContactSuburb'])
                    ->setState($currentResult['StudentContactState'])
                    ->setPostcode($currentResult['StudentContactPostCode'])
                ;

                $guardian1
                    ->getPhoneHome()
                    ->setNumber($currentResult['StudentContactPhoneActual'])
                    ->setSilent($currentResult['StudentContactSilentPhoneFlag'])
                ;

                $guardian1
                    ->getPhoneMobile()
                    ->setNumber($currentResult['StudentContactMobilePhone'])
                ;

                $guardian1
                    ->getPhoneWork()
                    ->setNumber($currentResult['StudentContactOccupPhone'])
                ;

                /* Guardian 2
                 */
                $guardian2Email = new Common\Email();
                $guardian2Email
                    ->setAddress($currentResult['StudentContactSpouseDefaultEmail'])
                ;

                $guardian2 = new Guardian();
                $guardian2
                    ->setId($currentResult['StudentContactSpouseID'])
                    ->setTitle($currentResult['StudentContactSpouseTitle'])
                    ->setPreferredName($currentResult['StudentContactSpousePreferred'])
                    ->setLastname($currentResult['StudentContactSpouseSurname'])
                    ->addEmail($guardian2Email)
                    ->setType($currentResult['StudentContactType'])
                    ->setRole($currentResult['StudentContactSpouseRelation'])
                ;

                $guardian2
                    ->getPhoneMobile()
                    ->setNumber($currentResult['StudentContactSpouseMobilePhone'])
                ;

                $guardian2
                    ->getPhoneWork()
                    ->setNumber($currentResult['StudentContactSpouseOccupPhone'])
                ;

                /* Compile student record with known data
                 */
                $student = new Student();
                $student
                    ->setId($currentResult['StudentID'])
                    ->setGivenName($currentResult['StudentGiven1'])
                    ->setPreferredName($currentResult['StudentGiven1'])
                    ->setLastname($currentResult['StudentSurname'])
                    ->setGuardiansSeparated($currentResult['StudentParentsSeparatedFlag'])
                ;

                /* Relations
                 */
                $student
                    ->getYear()
                    ->setLevel($currentResult['StudentYearLevel'])
                ;

                $student
                    ->getHouse()
                    ->setName($currentResult['StudentHouse'])
                ;

                // TODO: Refactor into adaptation layer

                if ($currentResult['StudentBoarder'] == 'Y') {
                    $boardingHouse = new Common\BoardingHouse();
                    $student->setBoardingHouse($boardingHouse);
                }

                /* Parents/Guardians
                 */
                foreach(array($guardian1, $guardian2) as $currentGuardian) {
                    if (strlen($currentGuardian->getPreferredName())) {
                        $student->getGuardians()->add($currentGuardian);
                    }
                }

                /* Stack it up
                 */
                $collection->add($student);
            }
        }

        return $collection;
    }

    /**
     * Get a bunch of boarder students and list their contacts
     *
     * TODO: This is a variation of getStudentContactListExtended()
     *
     * @return Collection
     */
    public function getStudentBoarderContactListExtended() {
        $collection = new Collection();

        $allResults = $this->getHandler()->getStudentBoarderContactListExtended();

        if (is_array($allResults) && count($allResults)) {
            foreach($allResults as $currentResult) {

                /* Guardian 1
                 */
                $guardian1Email = new Common\Email();
                $guardian1Email
                    ->setAddress($currentResult['StudentContactDefaultEmail'])
                ;

                $guardian1 = new Guardian();
                $guardian1
                    ->setId($currentResult['StudentContactID'])
                    ->setTitle($currentResult['StudentContactTitle'])
                    ->setPreferredName($currentResult['StudentContactPreferred'])
                    ->setLastname($currentResult['StudentContactSurname'])
                    ->addEmail($guardian1Email)
                    ->setPubliclyVisible($currentResult['PrimaryDirectoryIncludeFlag'])
                    ->setType($currentResult['StudentContactType'])
                ;

                $guardian1
                    ->getPostalAddress()
                        ->setAddress1($currentResult['StudentContactAddress1'])
                        ->setAddress2($currentResult['StudentContactAddress2'])
                        ->setAddress3($currentResult['StudentContactAddress3'])
                        ->setSuburb($currentResult['StudentContactSuburb'])
                        ->setState($currentResult['StudentContactState'])
                        ->setPostcode($currentResult['StudentContactPostCode'])
                        ->setCountry($currentResult['StudentContactCountryDesc'])
                ;

                $guardian1
                    ->getHomeAddress()
                        ->setAddress1($currentResult['StudentContactAddress1'])
                        ->setAddress2($currentResult['StudentContactAddress2'])
                        ->setAddress3($currentResult['StudentContactAddress3'])
                        ->setSuburb($currentResult['StudentContactSuburb'])
                        ->setState($currentResult['StudentContactState'])
                        ->setPostcode($currentResult['StudentContactPostCode'])
                        ->setCountry($currentResult['StudentContactCountryDesc'])
                ;

                $guardian1
                    ->getPhoneHome()
                        ->setNumber($currentResult['StudentContactPhoneActual'])
                        ->setSilent($currentResult['StudentContactSilentPhoneFlag'])
                ;

                $guardian1
                    ->getPhoneMobile()
                        ->setNumber($currentResult['StudentContactMobilePhone'])
                ;

                $guardian1
                    ->getPhoneWork()
                        ->setNumber($currentResult['StudentContactOccupPhone'])
                ;

                /* Guardian 2
                 */
                $guardian2Email = new Common\Email();
                $guardian2Email
                    ->setAddress($currentResult['StudentContactSpouseDefaultEmail'])
                ;

                $guardian2 = new Guardian();
                $guardian2
                    ->setId($currentResult['StudentContactSpouseID'])
                    ->setTitle($currentResult['StudentContactSpouseTitle'])
                    ->setPreferredName($currentResult['StudentContactSpousePreferred'])
                    ->setLastname($currentResult['StudentContactSpouseSurname'])
                    ->addEmail($guardian2Email)
                    ->setPubliclyVisible($currentResult['SpouseDirectoryIncludeFlag'])
                ;

                $guardian2
                    ->getPostalAddress()
                        ->setAddress1($currentResult['StudentContactSpouseAddress1'])
                        ->setAddress2($currentResult['StudentContactSpouseAddress2'])
                        ->setAddress3($currentResult['StudentContactSpouseAddress3'])
                        ->setSuburb($currentResult['StudentContactSpouseSuburb'])
                        ->setState($currentResult['StudentContactSpouseState'])
                        ->setPostcode($currentResult['StudentContactSpousePostCode'])
                        ->setCountry($currentResult['StudentContactSpouseCountryDesc'])
                ;

                $guardian2
                    ->getHomeAddress()
                        ->setAddress1($currentResult['StudentContactSpouseAddress1'])
                        ->setAddress2($currentResult['StudentContactSpouseAddress2'])
                        ->setAddress3($currentResult['StudentContactSpouseAddress3'])
                        ->setSuburb($currentResult['StudentContactSpouseSuburb'])
                        ->setState($currentResult['StudentContactSpouseState'])
                        ->setPostcode($currentResult['StudentContactSpousePostCode'])
                        ->setCountry($currentResult['StudentContactSpouseCountryDesc'])
                ;

                $guardian2
                    ->getPhoneHome()
                        ->setNumber($currentResult['StudentContactSpousePhoneActual'])
                        ->setSilent($currentResult['StudentContactSpouseSilentPhoneFlag'])
                ;

                $guardian2
                    ->getPhoneMobile()
                       ->setNumber($currentResult['StudentContactSpouseMobilePhone'])
                ;

                $guardian2
                    ->getPhoneWork()
                        ->setNumber($currentResult['StudentContactSpouseOccupPhone'])
                ;

                /* Compile student record with known data
                 */
                $student = new Student();
                $student
                    ->setId($currentResult['StudentID'])
                    ->setGivenName($currentResult['StudentGiven1'])
                    ->setPreferredName($currentResult['StudentGiven1'])
                    ->setLastname($currentResult['StudentSurname'])
                    ->setGuardiansSeparated($currentResult['StudentParentsSeparatedFlag'])
                ;

                /* Relations
                 */
                $student
                    ->getYear()
                        ->setLevel($currentResult['StudentYearLevel'])
                ;

                $student
                    ->getHouse()
                        ->setName($currentResult['StudentHouse'])
                ;

                // TODO: Refactor into adaptation layer

                if ($currentResult['StudentBoarder'] == 'Y') {
                    $boardingHouse = new Common\BoardingHouse();
                    $student->setBoardingHouse($boardingHouse);
                }

                /* Parents/Guardians
                 */
                foreach(array($guardian1, $guardian2) as $currentGuardian) {
                    if (strlen($currentGuardian->getPreferredName())) {
                        $student->getGuardians()->add($currentGuardian);
                    }
                }

                /* Stack it up
                 */
                $collection->add($student);
            }
        }

        return $collection;
    }

    /**
     * Gets a list of Contacts for all Students in a particular year level. The
     * year level is stored in the external db.
     *
     * Used in reporting.
     *
     * TODO: This should be the new master method, with optional args for
     * yearlevel, name, boardingStatus and form - each of which are used
     * in a queryBuilder-style construct
     *
     * @param $yearlevel
     * @param $name
     * @param $boardingStatus
     * @param $form
     *
     * @return Collection
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form) {
        $collection = new Collection();

        $allResults = $this->getHandler()->getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form);

        if (is_array($allResults) && count($allResults)) {
            foreach($allResults as $currentResult) {

                /* Guardian 1
                 */
                $guardian1Email = new Common\Email();
                $guardian1Email
                    ->setAddress($currentResult['StudentContactDefaultEmail'])
                ;

                $guardian1 = new Guardian();
                $guardian1
                    ->setId($currentResult['StudentContactID'])
                    ->setTitle($currentResult['StudentContactTitle'])
                    ->setPreferredName(strlen($currentResult['StudentContactPreferred']) ? $currentResult['StudentContactPreferred'] : $currentResult['StudentContactLegalFullName'])
                    ->setLastname($currentResult['StudentContactSurname'])
                    ->addEmail($guardian1Email)
                    ->setPubliclyVisible($currentResult['PrimaryDirectoryIncludeFlag'])
                    ->setType($currentResult['StudentContactType'])
                ;

                $guardian1
                    ->getPostalAddress()
                        ->setAddress1($currentResult['StudentContactAddress1'])
                        ->setAddress2($currentResult['StudentContactAddress2'])
                        ->setAddress3($currentResult['StudentContactAddress3'])
                        ->setSuburb($currentResult['StudentContactSuburb'])
                        ->setState($currentResult['StudentContactState'])
                        ->setPostcode($currentResult['StudentContactPostCode'])
                        ->setCountry($currentResult['StudentContactCountryCode'])
                ;

                $guardian1
                    ->getHomeAddress()
                        ->setAddress1($currentResult['StudentContactHomeAddress1'])
                        ->setAddress2($currentResult['StudentContactHomeAddress2'])
                        ->setAddress3($currentResult['StudentContactHomeAddress3'])
                        ->setSuburb($currentResult['StudentContactHomeSuburb'])
                        ->setState($currentResult['StudentContactHomeState'])
                        ->setPostcode($currentResult['StudentContactHomePostCode'])
                        ->setCountry($currentResult['StudentContactHomeCountryCode'])
                ;

                $guardian1
                    ->getPhoneHome()
                        ->setNumber($currentResult['StudentContactPhoneActual'])
                        ->setSilent($currentResult['StudentContactSilentPhoneFlag'])
                ;

                $guardian1
                    ->getPhoneMobile()
                       ->setNumber($currentResult['StudentContactMobilePhone'])
                ;

                $guardian1
                    ->getPhoneWork()
                       ->setNumber($currentResult['StudentContactOccupPhone'])
                ;

                /* Guardian 2
                 */
                $guardian2Email = new Common\Email();
                $guardian2Email
                    ->setAddress($currentResult['StudentContactSpouseDefaultEmail'])
                ;

                $guardian2 = new Guardian();
                $guardian2
                    ->setId($currentResult['StudentContactSpouseID'])
                    ->setTitle($currentResult['StudentContactSpouseTitle'])
                    ->setPreferredName(strlen($currentResult['StudentContactSpousePreferred']) ? $currentResult['StudentContactSpousePreferred'] : $currentResult['StudentContactSpouseLegalFullName'])
                    ->setLastname($currentResult['StudentContactSpouseSurname'])
                    ->addEmail($guardian2Email)
                    ->setPubliclyVisible($currentResult['SpouseDirectoryIncludeFlag'])
                ;

                $guardian2
                    ->getPostalAddress()
                        ->setAddress1($currentResult['StudentContactSpouseAddress1'])
                        ->setAddress2($currentResult['StudentContactSpouseAddress2'])
                        ->setAddress3($currentResult['StudentContactSpouseAddress3'])
                        ->setSuburb($currentResult['StudentContactSpouseSuburb'])
                        ->setState($currentResult['StudentContactSpouseState'])
                        ->setPostcode($currentResult['StudentContactSpousePostCode'])
                        ->setCountry($currentResult['StudentContactSpouseCountryDesc'])
                ;

                $guardian2
                    ->getHomeAddress()
                    ->setAddress1($currentResult['StudentContactSpouseAddress1'])
                    ->setAddress2($currentResult['StudentContactSpouseAddress2'])
                    ->setAddress3($currentResult['StudentContactSpouseAddress3'])
                    ->setSuburb($currentResult['StudentContactSpouseSuburb'])
                    ->setState($currentResult['StudentContactSpouseState'])
                    ->setPostcode($currentResult['StudentContactSpousePostCode'])
                    ->setCountry($currentResult['StudentContactSpouseCountryDesc'])
                ;

                $guardian2
                    ->getPhoneHome()
                        ->setNumber($currentResult['StudentContactSpousePhoneActual'])
                        ->setSilent($currentResult['StudentContactSpouseSilentPhoneFlag'])
                ;

                $guardian2
                    ->getPhoneMobile()
                        ->setNumber($currentResult['StudentContactSpouseMobilePhone'])
                ;

                $guardian2
                    ->getPhoneWork()
                        ->setNumber($currentResult['StudentContactSpouseOccupPhone'])
                ;

                /* Compile student record with known data
                 */
                $student = new Student();
                $student
                    ->setId($currentResult['StudentID'])
                    ->setGivenName($currentResult['StudentGiven1'])
                    ->setPreferredName($currentResult['StudentGiven1'])
                    ->setLastname($currentResult['StudentSurname'])
                    ->setGuardiansSeparated($currentResult['StudentParentsSeparatedFlag'])
                ;

                /* Relations
                 */
                $student
                    ->getYear()
                        ->setLevel($currentResult['StudentYearLevel'])
                ;

                $student
                    ->getForm()
                        ->setLevel($currentResult['StudentForm'])
                ;

                $student
                    ->getHouse()
                        ->setName($currentResult['StudentHouse'])
                ;

                // TODO: Refactor into adaptation layer

                if ($currentResult['StudentBoarder'] == 'Y') {
                    $boardingHouse = new Common\BoardingHouse();
                    $student->setBoardingHouse($boardingHouse);
                }

                /* Parents/Guardians
                 */
                foreach(array($guardian1, $guardian2) as $currentGuardian) {
                    if (strlen($currentGuardian->getPreferredName())) {
                        $student->getGuardians()->add($currentGuardian);
                    }
                }

                /* Stack it up
                 */
                $collection->add($student);
            }
        }

        return $collection;
    }

    /**
     * Gets all Students' account info from external db
     *
     * TODO: This is a variation of getStudentContactListExtended()
     *
     * @return Collection
     */
    public function getStudentUserAccountInfo() {
        $collection = new Collection();

        $allResults = $this->getHandler()->getStudentUserAccountInfo();

        if (is_array($allResults) && count($allResults)) {
            foreach ($allResults as $currentResult) {
                $student = new Student();
                $student
                    ->setId($currentResult['StudentID'])
                    ->setTitle($currentResult['StudentTitle'])
                    ->setPreferredName($currentResult['StudentPreferred'])
                    ->setLastname($currentResult['StudentSurname'])
                    ->setGender($currentResult['StudentGender'])
                ;

                $student
                    ->getYear()
                        ->setLevel($currentResult['StudentYearLevel'])
                ;

                $student
                    ->getHouse()
                        ->setName($currentResult['StudentHouse'])
                ;

                /* Assign emails
                 */
                $email1 = new Common\Email();
                $email1
                    ->setAddress($currentResult['StudentEmail']);

                $email2 = new Common\Email();
                $email2
                    ->setAddress($currentResult['StudentOccupEmail']);

                foreach(array($email1, $email2) as $currentEmail) {
                    if (strlen($currentEmail->getAddress())) {
                        $student->addEmail($currentEmail);
                    }
                }

                // TODO: Refactor into adaptation layer

                if ($currentResult['StudentBoarder'] == 'Y') {
                    $boardingHouse = new Common\BoardingHouse();
                    $boardingHouse
                        ->setName($currentResult['StudentBoardingHouse'])
                    ;

                    $student
                        ->setBoardingHouse($boardingHouse)
                    ;
                }

                /* Campus
                 */
                $campus = new Common\Campus();
                $campus
                    ->setName($currentResult['StudentCampus'])
                ;

                $student
                    ->getCampus()
                        ->add($campus)
                ;

                $collection->add($student);
            }
        }

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function getStudentUserAccountIterator()
    {
        return $this->getHandler()->getStudentUserAccountIterator();
    }

    /* Staff
     */

    /**
     * Get info for the staff member $externalUserId
     *
     * @param $externalUserId
     * @return Staff
     */
    public function getStaff($externalUserId) {
        $currentResult = $this->getHandler()->getStaff($externalUserId);

        $staff = new Staff();
        $staff
            ->getMeta()
                ->setExtension($currentResult['StaffExtension'])
                ->getCategory()
                    ->setCode($currentResult['SchoolStaffCode'])
        ;

        $staff
            ->getPhoneMobile()
                ->setNumber($currentResult['StaffMobilePhone'])
        ;

        /* Campus
         */
        $campus = new Common\Campus();
        $campus
            ->setName($currentResult['StaffCampusDescription'])
        ;

        $staff
            ->getCampus()
                ->add($campus)
        ;

        /* Type
         */
        $staff
            ->getMeta()
                ->setType($currentResult['StaffCategoryDescription'])
                ->setDepartment($currentResult['StaffDepartment'])
                ->setRoom($currentResult['StaffRoom'])
                ->setBuilding($currentResult['StaffBuilding'])
                ->setVoicemail($currentResult['StaffVoiceMail'])
        ;

        return $staff;
    }

    /**
     * Contact list for staff directory
     * Exclude people who aren't active.
     * Exclude people who are on leave (category description for this is 'Staff on leave')
     *
     * TODO: This is a variation of getStaff()
     *
     * @return Collection
     */
    public function getStaffContactListExtended() {
        $collection = new Collection();

        $allResults = $this->getHandler()->getStaffContactListExtended();

        if (is_array($allResults) && count($allResults)) {
            foreach ($allResults as $currentResult) {
                $staff = new Staff();

                $staff
                    ->setId($currentResult['StaffID'])
                    ->setTitle($currentResult['StaffTitle'])
                    ->setPreferredName($currentResult['StaffPreferredName'])
                    ->setLastname($currentResult['StaffSurname'])
                    ->getMeta()
                        ->setDepartment($currentResult['StaffDepartment'])
                        ->setExtension($currentResult['StaffExtension'])
                        ->getPosition()
                            ->setDescription($currentResult['JobPositionDescription'])
                            ->setStartDate(new \Alaress_Date_Format($currentResult['StaffJobPositionStartDate'], 'Y-m-d H:i:s'))
                            ->setEndDate(new \Alaress_Date_Format($currentResult['StaffJobPositionEndDate'], 'Y-m-d H:i:s'))
                ;

                $staff
                    ->getMeta()
                        ->getCategory()
                            ->setCode($currentResult['SchoolStaffCode'])
                            ->setType($currentResult['StaffCategoryType'])
                ;

                /* Phone
                 */
                $staff
                    ->getPhoneWork()
                        ->setNumber($currentResult['StaffOccupPhone'])
                ;

                /* Campus
                 */
                $campus = new Common\Campus();
                $campus
                    ->setName($currentResult['StaffCampusDescription']);

                $staff
                    ->getCampus()
                        ->add($campus)
                ;

                /* Email
                 */
                $email = new Common\Email();
                $email
                    ->setAddress($currentResult['StaffOccupEmail'])
                ;

                $staff
                    ->addEmail($email)
                ;

                /* Stack it up
                 */
                $collection->add($staff);
            }
        }

        return $collection;
    }

    /**
     * Gets all active Staff's account info from external db
     *
     * @return Collection
     */
    public function getStaffUserAccountInfo() {
        $collection = new Collection();

        $allResults = $this->getHandler()->getStaffUserAccountInfo();

        if (is_array($allResults) && count($allResults)) {
            foreach ($allResults as $currentResult) {
                $staff = new Staff();
                $staff
                    ->setId($currentResult['StaffID'])
                    ->setTitle($currentResult['StaffTitle'])
                    ->setPreferredName($currentResult['StaffPreferredName'])
                    ->setLastname($currentResult['StaffSurname'])
                    ->getMeta()
                        ->setDepartment($currentResult['StaffDepartment'])
                ;

                $staff
                    ->getMeta()
                        ->getCategory()
                            ->setType($currentResult['StaffCategoryType'])
                ;

                /* Account
                 */
                $staff
                    ->getAccount()
                        ->setLogin($currentResult['NetworkLogin'])
                        ->setActive($currentResult['ActiveFlag'])
                ;

                /* Email
                 */
                $email1 = new Common\Email();
                $email1
                    ->setAddress($currentResult['Email'])
                ;

                $email2 = new Common\Email();
                $email2
                    ->setAddress($currentResult['OccupEmail'])
                ;

                foreach (array($email1, $email2) as $currentEmail) {
                    if (strlen($currentEmail->getAddress())) {
                        $staff->addEmail($currentEmail);
                    }
                }

                /* Campus
                 */
                $campus = new Common\Campus();
                $campus
                    ->setName($currentResult['StaffCampus'])
                ;

                $staff
                    ->getCampus()
                        ->add($campus)
                ;

                /* Stack it up
                 */
                $collection->add($staff);
            }
        }

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function getStaffUserAccountIterator()
    {
        return $this->getHandler()->getStaffUserAccountIterator();
    }

    /**
     * Get a list of students belonging to $externalUserId
     *
     * @param $externalUserId
     * @return \Schoolbox\Collection
     */
    public function getEnrolledStudents($externalUserId) {
        $collection = new Collection();

        $allResults = $this->getHandler()->getEnrolledStudents($externalUserId);

        if (is_array($allResults) && count($allResults)) {
            foreach ($allResults as $currentResult) {
                $student = new Student();
                $student
                    ->setId($currentResult['RelatedID'])
                ;

                $collection->add($student);
            }
        }

        return $collection;
    }

    /* Misc
     */

    /**
     * Get an email address belonging to $externalUserId
     *
     * @param $externalUserId
     * @return Common\Email
     */
    public function getEmail($externalUserId) {
        $currentResult = $this->getHandler()->getEmail($externalUserId);

        $email = new Common\Email();
        $email->setAddress($currentResult);

        return $email;
    }

    /**
     * Set session vars for current semester (fileyear, filesemester)
     *
     * @param null $externalUserId
     * @return int
     */
    public function getCurrentSemester($externalUserId = null) {
        return $this->getHandler()->getCurrentSemester($externalUserId);
    }

    /**
     * Gets the current file year for this user
     *
     * @param null $externalUserId
     * @return int
     */
    public function getCurrentYear($externalUserId = null) {
        return $this->getHandler()->getCurrentYear($externalUserId);
    }

    /**
     * Get a list of subjects and their types
     *
     * @param $externalUserId
     * @param $userId
     *
     * @return Collection
     */
    public function getSubjectList($externalUserId, $userId) {
        $collection = new Collection();

        $allResults = $this->getHandler()->getSubjectList($externalUserId, $userId);

        if (is_array($allResults) && count($allResults)) {
            foreach ($allResults as $currentResult) {
                $campus = new Common\Campus();
                $campus
                    ->setCode($currentResult['campusCode'])
                ;

                $subject = new Student\Subject();
                $subject
                    ->setName($currentResult['name'])
                    ->setCode($currentResult['code'])
                    ->setType($currentResult['classType'])
                    ->setCampus($campus)
                ;

                /* Stack it up
                 */
                $collection->add($subject);
            }
        }

        return $collection;
    }

    /**
     * Return a housemaster for a particular house
     *
     * @param $houseId
     * @return Staff
     */
    public function getHousemasterByHouseId($houseId) {
        $currentResult = $this->getHandler()->getHousemasterByHouseId($houseId);

        $staff = new Staff();
        $staff->setId($currentResult);

        return $staff;
    }

    /**
     * Translate a $boarderValue into a human-readable variant
     * e.g. translates D, W into "Day" and B into "Boarder"
     *
     * @param $boarderValue
     * @return string
     */
    public function determineBoarderType($boarderValue) {
        return $this->getHandler()->determineBoarderType($boarderValue);
    }

    /**
     * List of campuses a staff member is involved in based on timetable
     *
     * @param int $staffID
     * @return array|bool
     */
    public function getCampusFromTimetable($staffID)
    {
        return $this->getHandler()->getCampusFromTimetable($staffID);
    }

    /**
     *
     * Get student forms
     *
     * @return array
     */
    public function getStudentForms() {
        return $this->getHandler()->getStudentForms();
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


