<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Profile\Field;

use Alaress\Database\Predicate\InvalidColumnException;
use DebugBar\DebugBarException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\User;

class Factory
{
    /**
     * Constructor
     *
     * @param \Schoolbox\Admin\Profile\Editor\Field\Mapper $fieldMapper
     */
    public function __construct(
        private \Schoolbox\Admin\Profile\Editor\Field\Mapper $fieldMapper,
        private \Schoolbox\UserMapper $userMapper,
        private \External_User $externalUser
    ) {
    }


    /**
     * Find the fields that were enabled for $viewee by admin,
     * that $viewer was able to see,
     * ordered by sequence ascending
     *
     * @param User $viewee
     * @param User $viewer
     * @return array
     * @throws DebugBarException
     * @throws Exception
     * @throws \DomainException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function produce(\Schoolbox\User $viewee, \Schoolbox\User $viewer): array
    {
        $payload = [];

        /* Data about $viewee that we'll put through the $renderableFields list
         */
        $referenceFields = $this->generateReferenceFields($viewee);

        /* Fields that $viewer can actually see on screen
         */
        $renderableFields = $this->fieldMapper->findRenderableFields($viewee, $viewer);

        new \Alaress_Probe($referenceFields, $renderableFields, $payload);

        /* The preprocessed payload that frontend can just use
         */
        return $payload;
    }


    /**
     * This is a hybrid data struct containing all the information about $viewee.
     * This has schoolbox.user specific fields like pronoun and campus etc.
     * combined with whatever the External_UserInterface fields contain. This method
     * returns a loosely-typed array struct that you can map directly to the
     * list of renderable fields as determined by admin.
     *
     * @param User $viewee
     * @return array
     * @throws InvalidColumnException
     * @throws MapperException
     * @throws \DomainException
     */
    public function generateReferenceFields(\Schoolbox\User $viewee): array
    {
        if ($viewee->getRole()->isStudent()) {
            return $this->generateStudentReferenceFields($viewee);
        }

        if ($viewee->getRole()->isParent()) {
            return $this->generateParentReferenceFields($viewee);
        }

        if ($viewee->getRole()->isStaff()) {
            return $this->generateStaffReferenceFields($viewee);
        }

        throw new \DomainException('Viewee has an unknown role type');
    }


    /**
     * Preprocesses $viewee (a student) and their corresponding External_UserInterface array
     *
     * @param User $viewee
     * @return array
     * @throws MapperException
     * @throws InvalidColumnException
     */
    private function generateStudentReferenceFields(\Schoolbox\User $viewee): array
    {
        $externalUser = [];

        if ($viewee->getExternalId()) {
            $externalUser = $this->externalUser->getStudent($viewee->getExternalId());
            if (isset($externalUser['AlertMessage']) && is_array($externalUser['AlertMessage'])) {
                $externalUser['AlertMessages'] = $externalUser['AlertMessage'];
            } else {
                $externalUser['AlertMessages'] = empty($externalUser['AlertMessage']) ? [] : [trim($externalUser['AlertMessage'])];
            }
            if (isset($externalUser['CourtOrderType']) && (trim($externalUser['CourtOrderType']) !== '')) {
                $externalUser['AlertMessages'][] = 'Court Order - ' . trim($externalUser['CourtOrderType']);
            }
        }

        /* Form Teacher
         * Display their name if you couldn't find them in the db
         * via their id (some sis don't have the id)
         */
        $formTeacher = null;

        if (isset($externalUser['TeacherFormID']) && (string)$externalUser['TeacherFormID'] !== '') {
            try {
                $formTeacher = $this->userMapper->findByExternalId($externalUser['TeacherFormID'], Role::STAFF);
            } catch (MapperException $e) {
                // noop
            }
        }

        if (!$formTeacher && isset($externalUser['StudentFormStaffName']) && (string)$externalUser['StudentFormStaffName'] !== '') {
            $formTeacher = $this->userMapper->createModel();
            $formTeacher->setLastname($externalUser['StudentFormStaffName']);
        }

        /* Tutor
         * Display their name if you couldn't find them in the db
         * via their id (some sis don't have the id)
         */
        $tutorTeacher = null;

        if (isset($externalUser['TeacherTutorID']) && (string)$externalUser['TeacherTutorID'] !== '') {
            try {
                $tutorTeacher = $this->userMapper->findByExternalId($externalUser['TeacherTutorID'], Role::STAFF);
            } catch (MapperException $e) {
                // noop
            }
        }

        if (!$tutorTeacher && isset($externalUser['StudentTutorName']) && (string)$externalUser['StudentTutorName'] !== '') {
            $tutorTeacher = $this->userMapper->createModel();
            $tutorTeacher->setLastname($externalUser['StudentTutorName']);
        }

        /* Housemaster, or Head of House
         * Display their name if you couldn't find them in the db
         * via their id (some sis don't have the id)
         */
        $hohTeacher = null;

        if (isset($externalUser['StudentHeadOfHouse']) && (string)$externalUser['StudentHeadOfHouse'] !== '') {
            if ($externalUser['HeadOfHouseID']) {
                try {
                    $hohTeacher = $this->userMapper->findByExternalId($externalUser['HeadOfHouseID'], Role::STAFF);
                } catch (MapperException $e) {
                    $hohTeacher = $this->userMapper->createModel();
                    $hohTeacher->setLastname($externalUser['StudentHeadOfHouse']);
                }
            } else {
                $hohTeacher = $this->userMapper->createModel();
                $hohTeacher->setLastname($externalUser['StudentHeadOfHouse']);
            }
        }

        /* Year Level Coordinator
         * Display their name if you couldn't find them in the db
         * via their id (some sis don't have the id)
         */
        $coordTeacher = null;

        if (!empty($externalUser['StudentYearLevelCoordinator'])) {
            if (!empty($externalUser['YearLevelCoordID'])) {
                try {
                    $coordTeacher = $this->userMapper->findByExternalId($externalUser['YearLevelCoordID'], Role::STAFF);
                } catch (MapperException $e) {
                    $coordTeacher = $this->userMapper->createModel();
                    $coordTeacher->setLastname($externalUser['StudentYearLevelCoordinator']);
                }
            } else {
                $coordTeacher = $this->userMapper->createModel();
                $coordTeacher->setLastname($externalUser['StudentYearLevelCoordinator']);
            }
        }

        /* Coaches (This is a custom work somewhere)
         *
         * Display their name if you couldn't find them in the db
         * via their id (some sis don't have the id)
         */
        $studentCoach = null;

        if (!empty($externalUser['StudentCoach'])) {
            if (!empty($externalUser['CoachID'])) {
                try {
                    $studentCoach = $this->userMapper->findByExternalId($externalUser['CoachID']);
                } catch (MapperException $e) {
                    $studentCoach = $this->userMapper->createModel();
                    $studentCoach->setLastname($externalUser['StudentCoach']);
                }
            } else {
                $studentCoach = $this->userMapper->createModel();
                $studentCoach->setLastname($externalUser['StudentCoach']);
            }
        }

        /* Hybrid Theory
         */
        $referenceFields = [
            'pronoun' => $viewee->getPronoun(),
            'email' => $viewee->getEmail(),
            'externalId' => $viewee->getExternalId(),
            'role' => $viewee->getRole()->getName(),
            'residentialHouse' => $viewee->getResidentialHouse()->getHouse(),
            'campus' => $viewee->getCampusCollection(),
            /* This is an arbitrary set of people associated with $viewee somehow
             * They might (or might not) have actual schoolbox user accounts
             */
            'formTeacher' => $formTeacher,
            'tutorTeacher' => $tutorTeacher,
            'hohTeacher' => $hohTeacher,
            'coordTeacher' => $coordTeacher,
            'studentCoach' => $studentCoach,
            /* TODO A specially crafted pastoral thing
             *  Push that in when the time comes
             */
            'pastoralFlags' => null
        ];

        return array_merge($externalUser, $referenceFields);
    }


    /**
     * Preprocesses $viewee (a parent) and their corresponding External_UserInterface array
     *
     * @param User $viewee
     * @return array
     */
    private function generateParentReferenceFields(\Schoolbox\User $viewee): array
    {
    }


    /**
     * Preprocesses $viewee (a staff) and their corresponding External_UserInterface array
     *
     * @param User $viewee
     * @return array
     */
    private function generateStaffReferenceFields(\Schoolbox\User $viewee): array
    {
    }
}
