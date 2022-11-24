<?php

/**
 * External interface for accessing users relationships as specified by the SIS
 *
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External;

use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder\Code\ClassCodeMatchFactory;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Schoolbox\Mapper\Exception as MapperException;

class User
{
    private \External_User|null $externalUser;
    private \External_Class|null $externalClass;
    private UserMapper|null $userMapper;
    private string|null $externalType;

    /**
     * Create the interface for checking a users relationships from a sis.
     * Can load multiple users at once, or will load just the relationship that hs been requested
     *
     * If you do not inject into this class, it will spawn the following:
     * ConfigMapper instance, to get the external db type
     * UserMapper instance, to build the user objects from the users relationships at load{relationship}
     * \External_User() and \External_Class()
     *
     * @param string|null          $externalType
     * @param \External_Class|null $externalClass
     * @param \External_User|null  $externalUser
     * @param UserMapper|null      $userMapper
     */
    public function __construct(string $externalType = null, \External_Class $externalClass = null, \External_User $externalUser = null, UserMapper $userMapper = null)
    {
        $this->externalType = $externalType;
        $this->externalClass = $externalClass;
        $this->externalUser = $externalUser;
        $this->userMapper = $userMapper;
        //if we are in a space where we are not injecting, spawn these classes correctly...
        if (null === $externalType) {
            $this->externalType = ConfigMapper::getInstance()->getConfigLoader()->get('external_type');
        }
        if (null === $externalClass) {
            $this->externalClass = new \External_Class();
        }
        if (null === $externalUser) {
            $this->externalUser = new \External_User();
        }
        if (null === $userMapper) {
            $this->userMapper = UserMapper::getInstance();
        }
    }

    /**
     * Finds the parents for each $students from the external database (sis) and will hydrate user objects for each $parent
     * These are all stored in the $this->parents[$student.ExternalID] = [$parent]
     * @param \Schoolbox\User[] $students
     * @return Collection
     */
    public function loadParents($students): Collection
    {
        $parents = new Collection();
        foreach ($students as $student) {
            $parents[$student->getExternalId()] = new GenericCollection(\Schoolbox\User::class);

            $allParents = $this->externalUser->getStudentContactList([$student->getExternalId()]);

            foreach ($allParents as $currentParent) {
                /* Add parent
                 */
                if (!empty($currentParent['StudentContactID'])) {
                    try {
                        $parent = $this->userMapper->findByExternalId($currentParent['StudentContactID'], Role::PARENT);
                        $parents[$student->getExternalId()]->add($parent);
                    } catch (MapperException $e) {
                        //noop
                    }
                }
                /* Add speece
                 */
                if (!empty($currentParent['StudentContactSpouseID'])) {
                    try {
                        $spouse = $this->userMapper->findByExternalId($currentParent['StudentContactSpouseID'], Role::PARENT);
                        $parents[$student->getExternalId()]->add($spouse);
                    } catch (MapperException $e) {
                        //noop
                    }
                }
            }
        }
        return $parents;
    }

    /**
     * Finds the teachers for each $students from the external database (sis) and will hydrate user objects for each $teacher
     * These are all stored in the $this->teachers[$student.ExternalID] = [$teacher]
     *
     * @param \Schoolbox\User[] $students
     * @return Collection
     */
    public function loadTeachers($students): Collection
    {
        $codeMatcher       = (new ClassCodeMatchFactory())->build($this->externalType);

        // Get all of the students subjects
        $teachers = new Collection();
        foreach ($students as $student) {
            $teachers[$student->getExternalId()] = new GenericCollection(\Schoolbox\User::class);
            $found                                     = [];
            $subjects                                  = $this->externalUser->getSubjectList($student);

            // Iterate and find teachers associated with each subject
            foreach ($subjects as $subject) {
                $teachers = $this->externalClass->getTeachers($codeMatcher::generateFolderCode($subject['campusCode'], $subject['code']));

                foreach ($teachers as $teacher) {
                    // If we haven't already, add this teacher to their collection

                    if (!isset($found[$teacher])) {
                        try {
                            $teachers[$student->getExternalId()]->add(
                                $this->userMapper->findByExternalId($teacher, Role::STAFF)
                            );

                            $found[$teacher] = 1;
                        } catch (\Schoolbox\Mapper\Exception $e) {
                            // Schoolbox user not found, lets not try and notify
                        }
                    }
                }
            }
        }
        return $teachers;
    }

    /**
     * Finds the children for each $parents from the external database (sis) and will hydrate user objects for each $child
     * These are all stored in the $this->children[$parent.ExternalID] = [$child]
     * @param \Schoolbox\User[] $parents
     * @return Collection
     */
    public function loadChildren($parents): Collection
    {
        $children = new Collection();
        foreach ($parents as $parent) {
            $children[$parent->getExternalId()] = new GenericCollection(\Schoolbox\User::class);
            $enrolments                             = $this->externalUser->getEnrolledStudents($parent->getExternalId());
            foreach ($enrolments as $child) {
                try {
                    $children[$parent->getExternalId()]->add($this->userMapper->findByExternalId($child['RelatedID']));
                } catch (MapperException $e) {
                    // noop
                }
            }
        }
        return $children;
    }
}
