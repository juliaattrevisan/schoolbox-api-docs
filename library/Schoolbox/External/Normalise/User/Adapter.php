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
            new \Alaress_Probe($currentResult);

            $guardianCollection = new \Schoolbox\Collection();

            $guardian1 = new Guardian(
                $currentResult['StudentContactId'],
                $currentResult['StudentContactTitle'],
                '',
                $currentResult['StudentContactPreferred'],
                $currentResult['StudentContactSurname'],
                '',
                '',
                '',
                new Common\Account(
                    '',
                    false
                ),
                new Common\Address(
                    $currentResult['StudentContactAddress1'],
                    $currentResult['StudentContactAddress2'],
                    $currentResult['StudentContactAddress3'],
                    $currentResult['StudentContactSuburb'],
                    $currentResult['StudentContactState'],
                    $currentResult['StudentContactPostCode'],
                    $currentResult['StudentContactCountryDesc']
                ),
                new Common\Address(
                    $currentResult['StudentContactAddress1'],
                    $currentResult['StudentContactAddress2'],
                    $currentResult['StudentContactAddress3'],
                    $currentResult['StudentContactSuburb'],
                    $currentResult['StudentContactState'],
                    $currentResult['StudentContactPostCode'],
                    $currentResult['StudentContactCountryDesc']
                ),
                $currentResult['StudentContactDefaultEmail'],
                new Common\Phone(
                    $currentResult['StudentContactPhoneActual'],
                    $currentResult['StudentContactSilentPhoneFlag']
                ),
                new Common\Phone(
                    $currentResult['StudentContactPhoneMobile'],
                    false
                ),
                new Common\Phone(
                    $currentResult['StudentContactOccupPhone'],
                    false
                ),
                new Collection(),
                $currentResult['PrimaryDirectoryIncludeFlag']
            );

            $guardian2 = new Guardian(
                $currentResult['StudentContactSpouseId'],
                $currentResult['StudentContactSpouseTitle'],
                '',
                $currentResult['StudentContactSpousePreferred'],
                $currentResult['StudentContactSpouseSurname'],
                '',
                '',
                '',
                new Common\Account(
                    '',
                    false
                ),
                new Common\Address(
                    $currentResult['StudentContactSpouseAddress1'],
                    $currentResult['StudentContactSpouseAddress2'],
                    $currentResult['StudentContactSpouseAddress3'],
                    $currentResult['StudentContactSpouseSuburb'],
                    $currentResult['StudentContactSpouseState'],
                    $currentResult['StudentContactSpousePostCode'],
                    $currentResult['StudentContactSpouseCountryDesc']
                ),
                new Common\Address(
                    $currentResult['StudentContactSpouseAddress1'],
                    $currentResult['StudentContactSpouseAddress2'],
                    $currentResult['StudentContactSpouseAddress3'],
                    $currentResult['StudentContactSpouseSuburb'],
                    $currentResult['StudentContactSpouseState'],
                    $currentResult['StudentContactSpousePostCode'],
                    $currentResult['StudentContactSpouseCountryDesc']
                ),
                $currentResult['StudentContactSpouseDefaultEmail'],
                new Common\Phone(
                    $currentResult['StudentContactSpousePhoneActual'],
                    false
                ),
                new Common\Phone(
                    $currentResult['StudentContactSpousePhoneMobile'],
                    false
                ),
                new Common\Phone(
                    $currentResult['StudentContactSpouseOccupPhone'],
                    false
                ),
                new Collection(),
                $currentResult['SpouseDirectoryIncludeFlag']
            );

            $student = new Student(
                $currentResult['StudentID'],
                '',
                $currentResult['StudentGiven1'],
                $currentResult['StudentGiven1'],
                $currentResult['StudentSurname'],
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                new Student\Year(
                    $currentResult['StudentYearLevel'],
                    new Staff()
                ),
                new Student\Form(
                    '',
                    new Staff()
                ),
                new Staff(),
                new Common\House(
                    '',
                    $currentResult['StudentHouse'],
                    '',
                    new Staff(),
                    false
                ),
                new Common\Account(),
                new Common\Address(),
                new Common\Address(),
                '',
                new Common\Phone(),
                new Common\Phone(),
                $guardianCollection,
                false,
                new \Schoolbox\Collection(),
                false
            );

            /* Stack it up
             */
            $collection->add($student);

            // TODO: Populate nice objects with crappy array data
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