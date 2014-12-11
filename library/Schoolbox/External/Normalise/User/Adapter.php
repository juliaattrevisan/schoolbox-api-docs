<?php
/**
 * Adapter
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
     * Get a bunch of students
     *
     * @return Collection
     */
    public function getStudentBoarderContactListExtended() {
        $collection = new Collection();

        $allResults = $this->getHandler()->getStudentBoarderContactListExtended();

        foreach($allResults as $currentResult) {
            $guardian1 = new Guardian();
            $guardian1
                ->setId($currentResult['StudentContactId'])
                ->setTitle($currentResult['StudentContactTitle'])
                ->setPreferredName($currentResult['StudentContactPreferred'])
                ->setLastname($currentResult['StudentContactSurname'])
                ->setPostalAddress(new Common\Address(
                    $currentResult['StudentContactAddress1'],
                    $currentResult['StudentContactAddress2'],
                    $currentResult['StudentContactAddress3'],
                    $currentResult['StudentContactSuburb'],
                    $currentResult['StudentContactState'],
                    $currentResult['StudentContactPostCode'],
                    $currentResult['StudentContactCountryDesc']
                ))
                ->setHomeAddress(new Common\Address(
                    $currentResult['StudentContactAddress1'],
                    $currentResult['StudentContactAddress2'],
                    $currentResult['StudentContactAddress3'],
                    $currentResult['StudentContactSuburb'],
                    $currentResult['StudentContactState'],
                    $currentResult['StudentContactPostCode'],
                    $currentResult['StudentContactCountryDesc']
                ))
                ->setEmail($currentResult['StudentContactDefaultEmail'])
                ->setPhoneHome(new Common\Phone(
                    $currentResult['StudentContactPhoneActual'],
                    $currentResult['StudentContactSilentPhoneFlag']
                ))
                ->setPhoneMobile(new Common\Phone(
                    $currentResult['StudentContactPhoneMobile'],
                    false
                ))
                ->setPhoneWork(new Common\Phone(
                    $currentResult['StudentContactOccupPhone'],
                    false
                ))
                ->setPubliclyVisible($currentResult['PrimaryDirectoryIncludeFlag']);

            $guardian2 = new Guardian();
            $guardian2
                ->setId($currentResult['StudentContactSpouseId'])
                ->setTitle($currentResult['StudentContactSpouseTitle'])
                ->setPreferredName($currentResult['StudentContactSpousePreferred'])
                ->setLastname($currentResult['StudentContactSpouseSurname'])
                ->setPostalAddress(new Common\Address(
                    $currentResult['StudentContactSpouseAddress1'],
                    $currentResult['StudentContactSpouseAddress2'],
                    $currentResult['StudentContactSpouseAddress3'],
                    $currentResult['StudentContactSpouseSuburb'],
                    $currentResult['StudentContactSpouseState'],
                    $currentResult['StudentContactSpousePostCode'],
                    $currentResult['StudentContactSpouseCountryDesc']
                ))
                ->setHomeAddress(new Common\Address(
                    $currentResult['StudentContactSpouseAddress1'],
                    $currentResult['StudentContactSpouseAddress2'],
                    $currentResult['StudentContactSpouseAddress3'],
                    $currentResult['StudentContactSpouseSuburb'],
                    $currentResult['StudentContactSpouseState'],
                    $currentResult['StudentContactSpousePostCode'],
                    $currentResult['StudentContactSpouseCountryDesc']
                ))
                ->setEmail($currentResult['StudentContactSpouseDefaultEmail'])
                ->setPhoneHome(new Common\Phone(
                    $currentResult['StudentContactSpousePhoneActual'],
                    $currentResult['StudentContactSpouseSilentPhoneFlag']
                ))
                ->setPhoneMobile(new Common\Phone(
                    $currentResult['StudentContactSpousePhoneMobile'],
                    false
                ))
                ->setPhoneWork(new Common\Phone(
                    $currentResult['StudentContactSpouseOccupPhone'],
                    false
                ))
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