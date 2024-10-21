<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Profile\Field;

use Alaress\Database\Predicate\InvalidColumnException;
use DebugBar\DebugBarException;
use Schoolbox\Admin\Profile\Editor\Field\Mapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Factory
{
    /**
     * Constructor
     *
     * @param Mapper $fieldMapper
     * @param UserMapper $userMapper
     * @param \External_UserInterface $externalUser
     */
    public function __construct(
        private \Schoolbox\Admin\Profile\Editor\Field\Mapper $fieldMapper,
        private \Schoolbox\UserMapper $userMapper,
        private \External_UserInterface $externalUser
    ) {
    }


    /**
     * Find the fields that were enabled for $viewee by admin,
     * that $viewer was able to see,
     * ordered by sequence ascending
     *
     * @param User $viewee
     * @param User $viewer
     * @param callable|null $mutator
     * @return array
     * @throws DebugBarException
     * @throws DefinitionException
     * @throws InvalidColumnException
     * @throws MapperException
     * @throws \DomainException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function produce(\Schoolbox\User $viewee, \Schoolbox\User $viewer, ?callable $mutator = null): array
    {
        $payload = [
            'locale' => null,
            'roleTypes' => [
                'roleType' => $viewee->getRole()->getType(),
                'fields' => [
                    'selected' => []
                ]
            ]
        ];

        /* Data about $viewee that we'll put through the $renderableFields list
         */
        $referenceFields = $this->generateReferenceFields($viewee, $mutator);

        /* Fields that $viewer can actually see on screen
         */
        $renderableFields = $this->fieldMapper->findRenderableFields($viewee, $viewer);

        /** @var \Schoolbox\Admin\Profile\Editor\Field\Model $renderableField */
        foreach ($renderableFields as $renderableField) {
            $payload['roleTypes']['fields']['selected'][] = [
                'field' => $renderableField->getField(),
                'displayAs' => $renderableField->getDisplayAs(),
                'terminology' => $renderableField->getTerm(),
                'icon' => $renderableField->getIcon(),
                'css' => $renderableField->getCss(),
                'clickToReveal' => $renderableField->getClickToReveal(),
                'value' => $referenceFields[$renderableField->getField()] ?? null
            ];
        }

        /* The preprocessed payload that frontend can just use
         */
        return [
            'data' => $payload
        ];
    }


    /**
     * This is a hybrid data struct containing all the information about $viewee.
     * This has schoolbox.user specific fields like pronoun and campus etc.
     * combined with whatever the External_UserInterface fields contain. This method
     * returns a loosely-typed array struct that you can map directly to the
     * list of renderable fields as determined by admin.
     *
     * @param User $viewee
     * @param callable|null $mutator
     * @return array
     * @throws InvalidColumnException
     * @throws MapperException
     * @throws \DomainException
     */
    public function generateReferenceFields(\Schoolbox\User $viewee, ?callable $mutator): array
    {
        if ($viewee->getRole()->isStudent()) {
            return $this->generateStudentReferenceFields($viewee, $mutator);
        }

        if ($viewee->getRole()->isParent()) {
            return $this->generateParentReferenceFields($viewee, $mutator);
        }

        if ($viewee->getRole()->isStaff()) {
            return $this->generateStaffReferenceFields($viewee, $mutator);
        }

        throw new \DomainException('Viewee has an unknown role type');
    }


    /**
     * Preprocesses $viewee (a student) and their corresponding External_UserInterface array
     *
     * @param User $viewee
     * @param callable|null $mutator
     * @return array
     * @throws InvalidColumnException
     * @throws MapperException
     */
    private function generateStudentReferenceFields(\Schoolbox\User $viewee, ?callable $mutator): array
    {
        $externalUser = [];

        if ($viewee->getExternalId()) {
            $externalUser = $this->externalUser->getStudent($viewee->getExternalId());
            if (isset($externalUser['AlertMessage']) && is_array($externalUser['AlertMessage'])) {
                $externalUser['AlertMessages'] = $externalUser['AlertMessage'];
            } else {
                $externalUser['AlertMessages'] = empty($externalUser['AlertMessage']) ? [] : [trim($externalUser['AlertMessage'])];
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
            'pronoun' => (string)$viewee->getPronoun(),
            'email' => $viewee->getEmail(),
            'externalId' => $viewee->getExternalId(),
            'role' => $viewee->getRole()->getName(),
            'residentialHouse' => $viewee->getResidentialHouse()?->getHouse(),
            'campusCollection' => $viewee->getCampusCollection(),
            /* This is an arbitrary set of people associated with $viewee somehow
             * They might (or might not) have actual schoolbox user accounts
             */
            'formTeacher' => $formTeacher,
            'tutorTeacher' => $tutorTeacher,
            'hohTeacher' => $hohTeacher,
            'coordTeacher' => $coordTeacher,
            'studentCoach' => $studentCoach,
            /* Pastoral Counts was obtained specially in the controller cos it needs
             * a bunch of little bits and pieces; use the callback to populate it
             */
            'pastoralCounts' => []
        ];

        $result = array_merge($externalUser, $referenceFields);

        /* Mutate $result with an optional callback...such as that pastoral counts stuff
         */
        if (null !== $mutator) {
            $result = $mutator($result);
        }

        return $result;
    }


    /**
     * Preprocesses $viewee (a parent) and their corresponding External_UserInterface array
     *
     * @param User $viewee
     * @param callable|null $mutator
     * @return array
     */
    private function generateParentReferenceFields(\Schoolbox\User $viewee, ?callable $mutator): array
    {
        return [];
    }


    /**
     * Preprocesses $viewee (a staff) and their corresponding External_UserInterface array
     *
     * @param User $viewee
     * @param callable|null $mutator
     * @return array
     */
    private function generateStaffReferenceFields(\Schoolbox\User $viewee, ?callable $mutator): array
    {
        return [];
    }
}
