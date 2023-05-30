<?php

/**
 * External interface for loading users relationships as specified by the SIS
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
     * Hydrator to load relationships for users from external databases
     * Can load multiple users at once, or will load just the relationship that has been requested
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
     * Then return the collection
     *
     * @param Collection|\Schoolbox\User[] $students
     * @return  Collection|\Schoolbox\User[]
     */
    public function loadParents(Collection|array $students): Collection|array
    {
        foreach ($students as $student) {
            //if parents have already been loaded, the just continue
            if (!$student->getRole()->isStudent() || null !== $student->getParents()) {
                continue;
            }
            $parents = new GenericCollection(\Schoolbox\User::class);
            /* if the student does not have an externalID
             * set an empty array and continue
             */
            if ('' === trim((string)$student->getExternalId())) {
                $student->setParents($parents);
                continue;
            }

            $allParents = $this->externalUser->getStudentContactList([$student->getExternalId()]);

            foreach ($allParents as $currentParent) {
                /* Add parent
                 */
                if (!empty($currentParent['StudentContactID'])) {
                    try {
                        $parent = $this->userMapper->findByExternalId($currentParent['StudentContactID'], Role::PARENT);
                        $parents->add($parent);
                    } catch (MapperException $e) {
                        //noop
                    }
                }
                /* Add speece
                 */
                if (!empty($currentParent['StudentContactSpouseID'])) {
                    try {
                        $spouse = $this->userMapper->findByExternalId($currentParent['StudentContactSpouseID'], Role::PARENT);
                        $parents->add($spouse);
                    } catch (MapperException $e) {
                        //noop
                    }
                }
            }
            $student->setParents($parents);
        }
        return $students;
    }

    /**
     * Finds the teachers for each $students from the external database (sis) and will hydrate user objects for each $teacher
     * Then return the collection
     *
     * @param \Schoolbox\User[] $students
     * @return  Collection|\Schoolbox\User[]
     */
    public function loadTeachers(array $students): Collection|array
    {
        $codeMatcher       = (new ClassCodeMatchFactory())->build($this->externalType);

        // Get all of the students subjects
        foreach ($students as $student) {
            //if teachers have already been loaded, the just continue
            if (!$student->getRole()->isStudent() || null !== $student->getTeacherCollection()) {
                continue;
            }
            $teachersLoaded = new GenericCollection(\Schoolbox\User::class);
            $found                                     = [];
            $subjects                                  = $this->externalUser->getSubjectList($student);

            // Iterate and find teachers associated with each subject
            foreach ($subjects as $subject) {
                $teachers = $this->externalClass->getTeachers($codeMatcher::generateFolderCode($subject['campusCode'], $subject['code']));

                foreach ($teachers as $teacher) {
                    // If we haven't already, add this teacher to their collection

                    if (!isset($found[$teacher])) {
                        try {
                            $teachersLoaded->add(
                                $this->userMapper->findByExternalId($teacher, Role::STAFF)
                            );

                            $found[$teacher] = 1;
                        } catch (\Schoolbox\Mapper\Exception $e) {
                            // Schoolbox user not found, lets not try and notify
                        }
                    }
                }
            }
            $student->setTeacherCollection($teachersLoaded);
        }
        return $students;
    }

    /**
     * Finds the children for each $parents from the external database (sis) and will hydrate user objects for each $child
     * Then return the collection
     *
     * @param Collection|\Schoolbox\User[] $parents
     * @return \Schoolbox\User[]|Collection
     */
    public function loadChildren(Collection|array $parents): Collection|array
    {
        foreach ($parents as $parent) {
            //if children have already been loaded, the just continue
            if (!$parent->getRole()->isParent() || null !== $parent->getChildren()) {
                continue;
            }
            $children = new GenericCollection(\Schoolbox\User::class);
            /* if the parent does not have an externalID
             * set an empty array and continue
             */
            if ('' === trim((string)$parent->getExternalId())) {
                $parent->setChildren($children);
                continue;
            }

            $enrolments                             = $this->externalUser->getEnrolledStudents($parent->getExternalId());
            foreach ($enrolments as $child) {
                try {
                    $children->add($this->userMapper->findByExternalId($child['RelatedID']));
                } catch (MapperException $e) {
                    // noop
                }
            }
            $parent->setChildren($children);
        }
        return $parents;
    }
}
