<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Profile\Field;

use Schoolbox\Admin\Profile\Editor\Field\Mapper;
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
     * $mutatorIndex is a set of optional callbacks indexed by field name that
     * allows you to fetch its particular value from an expensive place only
     * when it's needed.
     * @param User $viewee
     * @param User $viewer
     * @param array|null $mutatorIndex
     * @return array[]
     * @throws MapperException
     * @throws \DomainException
     * @throws \UnexpectedValueException
     */
    public function produce(\Schoolbox\User $viewee, \Schoolbox\User $viewer, ?array $mutatorIndex = []): array
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
        $referenceFields = $this->generateReferenceFields($viewee);

        /* Fields that $viewer can actually see on screen
         */
        $renderableFields = $this->fieldMapper->findRenderableFields($viewee, $viewer);

        /** @var \Schoolbox\Admin\Profile\Editor\Field\Model $renderableField */
        foreach ($renderableFields as $renderableField) {
            $node = [
                'field' => $renderableField->getField(),
                'displayAs' => $renderableField->getDisplayAs(),
                'terminology' => $renderableField->getTerm(),
                'icon' => $renderableField->getIcon(),
                'css' => $renderableField->getCss(),
                'clickToReveal' => $renderableField->getClickToReveal(),
                'value' => $referenceFields[$renderableField->getField()] ?? null
            ];

            /* Some aftermarket data that isn't part of the externals and needs to be fetched
             * from a context outside of this factory. This started with Pastoral Counts that
             * needs various controller-specific instances that are annoying to inject here
             *
             * Find out if $mutator has a callback indexed by the current renderableField's name
             * If there is, use that as the value
             */
            if (isset($mutatorIndex[$renderableField->getField()])) {
                $node['value'] = $mutatorIndex[$renderableField->getField()]();
            }

            $payload['roleTypes']['fields']['selected'][] = $node;
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
     * @return array
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

        /* For convenience, collate the campus names here
         */
        $campusNames = '';

        if ($viewee->getCampusCollection()) {
            $campusNames = implode(', ', $viewee->getCampusCollection()->indexBy('name')->keys());
        }
        $tutorTeacher = $this->userMapper->findById(23);
        $formTeacher = $this->userMapper->findById(24);

        /* Hybrid Theory
         */
        $referenceFields = [
            'pronoun' => (string)$viewee->getPronoun(),
            'email' => $viewee->getEmail(),
            'externalId' => $viewee->getExternalId(),
            'role' => $viewee->getRole()->getName(),
            'positionTitle' => $viewee->getPositionTitle(),
            /* Favour schoolbox's data over the sis' data
             */
            'schoolHouse' => $viewee->getSchoolHouse()?->getHouse(),
            /* ResidentialHouse can indeed be null: storm and phpstannis have different ideas
             */
            /** @phpstan-ignore-next-line */
            'residentialHouse' => $viewee->getResidentialHouse()?->getHouse(),
            'yearLevel' => $viewee->getYear()?->getName() ?? '',
            'campusCollection' => $campusNames,
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
            'pastoralCounts' => null
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
        $externalUser = [];

        if ($viewee->getExternalId()) {
            $externalUser = $this->externalUser->getParent($viewee->getExternalId());
        }

        /* Only obtain spousal information if it had a chance of being visible
         * SpouseDirectoryIncludeFlag will stop them from being seen
         */
        $spouse = null;

        if (isset($externalUser['SpouseID']) && $externalUser['SpouseDirectoryIncludeFlag']) {
            try {
                $spouse = $this->userMapper->findByExternalId($externalUser['SpouseID'], Role::PARENT);
            } catch (MapperException $e) {
                // noop: spouse not found
            }
        }

        /* Only show a spouse if they have meaningful data
         */
        $spouseNode = null;

        /* Assign schoolbox's version of a spouse's details, but we need multiple data points:
         * The name is formatted with a hyperlink to a user profile screen since this spouse
         * has a schoolbox user account. We need their id and name as a struct...so, it's some
         * special handling but we can do that
         */
        if ($spouse) {
            $spouseValue = "{$spouse->getTitle()} {$spouse->getPreferredName()} {$spouse->getLastname()}";

            if ($spouse->getId() && '' !== trim($spouseValue)) {
                $spouseNode = [
                    'id' => $spouse->getId(),
                    'value' => $spouseValue
                ];
            }
        }

        /* For convenience, collate the campus names here
         */
        $campusNames = '';

        if ($viewee->getCampusCollection()) {
            $campusNames = implode(', ', $viewee->getCampusCollection()->indexBy('name')->keys());
        }

        /* Hybrid Theory
         */
        $referenceFields = [
            'pronoun' => (string)$viewee->getPronoun(),
            'email' => $viewee->getEmail(),
            'externalId' => $viewee->getExternalId(),
            'role' => $viewee->getRole()->getName(),
            'positionTitle' => $viewee->getPositionTitle(),
            /* Favour schoolbox's data over the sis' data
             */
            'campusCollection' => $campusNames,
            'spouseName' => $spouseNode,
        ];

        return array_merge($externalUser, $referenceFields);
    }


    /**
     * Preprocesses $viewee (a staff) and their corresponding External_UserInterface array
     *
     * @param User $viewee
     * @return array
     */
    private function generateStaffReferenceFields(\Schoolbox\User $viewee): array
    {
        $externalUser = [];

        if ($viewee->getExternalId()) {
            $externalUser = $this->externalUser->getStaff($viewee->getExternalId());
        }

        /* For convenience, collate the campus names here
         */
        $campusNames = '';

        if ($viewee->getCampusCollection()) {
            $campusNames = implode(', ', $viewee->getCampusCollection()->indexBy('name')->keys());
        }

        /* Hybrid Theory
         */
        $referenceFields = [
            'pronoun' => (string)$viewee->getPronoun(),
            'email' => $viewee->getEmail(),
            'externalId' => $viewee->getExternalId(),
            'role' => $viewee->getRole()->getName(),
            'positionTitle' => $viewee->getPositionTitle(),
            /* Favour schoolbox's data over the sis' data
             */
            'schoolHouse' => $viewee->getSchoolHouse()?->getHouse(),
            /* ResidentialHouse can indeed be null: storm and phpstannis have different ideas
             */
            /** @phpstan-ignore-next-line */
            'residentialHouse' => $viewee->getResidentialHouse()?->getHouse(),
            'yearLevel' => $viewee->getYear()?->getName() ?? '',
            'campusCollection' => $campusNames,
        ];

        return array_merge($externalUser, $referenceFields);
    }
}
