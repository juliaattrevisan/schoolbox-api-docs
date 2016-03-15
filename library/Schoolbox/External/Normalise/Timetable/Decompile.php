<?php
/**
 * Decompile
 *
 * Decompile an NDS Timetable back into an array
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\Timetable;


class Decompile
{

    /**
     * A Timetable instance
     *
     * @var Timetable
     */
    private $timetable = null;

    /**
     * RetroTimetable constructor.
     *
     * @param Timetable $timetable
     */
    public function __construct(Timetable $timetable)
    {
        $this->setTimetable($timetable);
    }

    /**
     * Decompile an NDS Timetable back into an array
     */
    public function decompile()
    {
        $timetable = [];

        foreach ($this->getTimetable()->getAllDays() as $currentDay) {
            $dayIndex = $currentDay->getNumber();

            foreach ($currentDay->getAllPeriods() as $currentPeriod) {
                $periodIndex = $currentPeriod->getNumber() - 1;

                /* Initialise Period struct if empty
                 */
                if (!is_array($timetable[$periodIndex])) {
                    $timetable[$periodIndex] = [
                        'periodName'     => $currentPeriod->getName(),
                        'periodStart'    => $currentPeriod->getStart(),
                        'periodEnd'      => $currentPeriod->getEnd(),
                        'periodNumber'   => $currentPeriod->getNumber(),
                        'periodSequence' => $currentPeriod->isTeaching() ? 1 : 2,
                        'day'            => []
                    ];
                }

                /* Decompile Classes
                 */
                foreach ($currentPeriod->getAllClasses() as $currentClass) {
                    $classInfo = [
                        'description' => $currentClass->getName(),
                        'code' => $currentClass->getCode(),
                        'campus' => $currentClass->getCampus()->getExtTimetableCode(),
                        'room' => $currentClass->getRoom(),
                    ];

                    /* Only include Staff members if they exist
                     * For classes with multiple teachers, just choose first()
                     */
                    if ($currentClass->getAllStaff()->count()) {
                        $staffMember = $currentClass->getAllStaff()->first();

                        $staffInfo = [
                            'staffID'        => $staffMember->getId(),
                            'staffFirstname' => $staffMember->getPreferredName(),
                            'staffSurname'   => $staffMember->getLastname(),
                            'staffTitle'     => $staffMember->getTitle()
                        ];
                    }
                    else {
                        $staffInfo = [
                            'staffID'        => '',
                            'staffFirstname' => '',
                            'staffSurname'   => '',
                            'staffTitle'     => ''
                        ];
                    }

                    $data = array_merge($classInfo, $staffInfo);

                    $timetable[$periodIndex]['day'][$dayIndex][$currentClass->getCode()] = $data;
                }
            }
        }

        return $timetable;
    }

    /* Getters/Setters
     */

    /**
     * Get timetable
     *
     * @return Timetable
     */
    private function getTimetable()
    {
        return $this->timetable;
    }

    /**
     * Set timetable
     *
     * @param Timetable $timetable
     */
    private function setTimetable($timetable)
    {
        $this->timetable = $timetable;
    }
}