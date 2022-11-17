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
    private Collection $parents;
    private Collection $children;
    private Collection $teachers;
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

        $this->parents = new Collection();
        $this->children = new Collection();
        $this->teachers = new Collection();
    }

    /**
     * Checks if the parents for $student have been loaded.
     * If not, it will load them from the externalDB
     * Then just return the collection of hydrated parents for $student
     *
     * @param \Schoolbox\User $student
     * @return \Schoolbox\User[]|GenericCollection
     */
    public function getParents(\Schoolbox\User $student)
    {
        //load students if they have not been loaded
        if (empty($this->parents) || empty($this->parents[$student->getExternalId()])) {
            $this->loadParents([$student]);
        }
        //return $this->parents
        return $this->parents[$student->getExternalId()];
    }

    /**
     * Checks if the teachers for $student have been loaded.
     * If not, it will load them from the externalDB
     * Then just return the collection of hydrated teachers for $student
     *
     * @param \Schoolbox\User $student
     * @return \Schoolbox\User[]|GenericCollection
     */
    public function getTeachers(\Schoolbox\User $student)
    {
        //load teachers if they have not been loaded
        if (empty($this->teachers) || empty($this->teachers[$student->getExternalId()])) {
            $this->loadTeachers([$student]);
        }
        //return teachers
        return $this->teachers[$student->getExternalId()];
    }

    /**
     * Checks if the children for $parent have been loaded.
     * If not, it will load them from the externalDB
     * Then just return the collection of hydrated students for $parent
     *
     * @param \Schoolbox\User $parent
     * @return \Schoolbox\User[]|GenericCollection
     */
    public function getChildren(\Schoolbox\User $parent)
    {
        //load students if they have not been loaded
        if (empty($this->children) || empty($this->children[$parent->getExternalId()])) {
            $this->loadChildren([$parent]);
        }
        //return students
        return $this->children[$parent->getExternalId()];
    }

    /**
     * Finds the parents for each $students from the external database (sis) and will hydrate user objects for each $parent
     * These are all stored in the $this->parents[$student.ExternalID] = [$parent]
     * @param \Schoolbox\User[] $students
     * @return void
     */
    public function loadParents($students): void
    {
        foreach ($students as $student) {
            $this->parents[$student->getExternalId()] = new GenericCollection(\Schoolbox\User::class);

            $allParents = $this->externalUser->getStudentContactList([$student->getExternalId()]);

            foreach ($allParents as $currentParent) {
                /* Add parent
                 */
                if (!empty($currentParent['StudentContactID'])) {
                    try {
                        $parent = $this->userMapper->findByExternalId($currentParent['StudentContactID'], Role::PARENT);
                        $this->parents[$student->getExternalId()]->add($parent);
                    } catch (MapperException $e) {
                        //noop
                    }
                }
                /* Add speece
                 */
                if (!empty($currentParent['StudentContactSpouseID'])) {
                    try {
                        $spouse = $this->userMapper->findByExternalId($currentParent['StudentContactSpouseID'], Role::PARENT);
                        $this->parents[$student->getExternalId()]->add($spouse);
                    } catch (MapperException $e) {
                        //noop
                    }
                }
            }
        }
    }

    /**
     * Finds the teachers for each $students from the external database (sis) and will hydrate user objects for each $teacher
     * These are all stored in the $this->teachers[$student.ExternalID] = [$teacher]
     *
     * @param \Schoolbox\User[] $students
     * @return void
     */
    public function loadTeachers($students): void
    {
        $codeMatcher       = (new ClassCodeMatchFactory())->build($this->externalType);

        // Get all of the students subjects

        foreach ($students as $student) {
            $this->teachers[$student->getExternalId()] = new GenericCollection(\Schoolbox\User::class);
            $found                                     = [];
            $subjects                                  = $this->externalUser->getSubjectList($student);

            // Iterate and find teachers associated with each subject
            foreach ($subjects as $subject) {
                $teachers = $this->externalClass->getTeachers($codeMatcher::generateFolderCode($subject['campusCode'], $subject['code']));

                foreach ($teachers as $teacher) {
                    // If we haven't already, add this teacher to their collection

                    if (!isset($found[$teacher])) {
                        try {
                            $this->teachers[$student->getExternalId()]->add(
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
    }

    /**
     * Finds the children for each $parents from the external database (sis) and will hydrate user objects for each $child
     * These are all stored in the $this->children[$parent.ExternalID] = [$child]
     * @param \Schoolbox\User[] $parents
     * @return void
     */
    public function loadChildren($parents): void
    {
        foreach ($parents as $parent) {
            $this->children[$parent->getExternalId()] = new GenericCollection(\Schoolbox\User::class);
            $enrolments                             = $this->externalUser->getEnrolledStudents($parent->getExternalId());
            foreach ($enrolments as $child) {
                try {
                    $this->children[$parent->getExternalId()]->add($this->userMapper->findByExternalId($child['RelatedID']));
                } catch (MapperException $e) {
                    // noop
                }
            }
        }
    }

    /**
     * Is this $parent a parent of $child?
     *
     * If the parents or children of the users have not been loaded
     * this will hit the external db and load them
     *
     * First checks if any of $child's parents is $parent
     * Then if that is inconclusive then check if any of $parent's children is $child
     * I'm guessing this is due with split families, a child / parent relationship might be 1 direction
     *
     * @param \Schoolbox\User $parent
     * @param \Schoolbox\User $child
     * @return bool
     */
    public function isParentOf(\Schoolbox\User $parent, \Schoolbox\User $child): bool
    {
        /* Parents were never initialised for $child
         * Their ancestry is inconclusive; continue
         */
        $parents = $this->getParents($child);
        foreach ($parents as $parentTest) {
            if ($parentTest->getId() == $parent->getId()) {
                return true;
            }
        }

        /* $this->children was never initialised for this user
         * Their descendents are inconclusive; continue
         */
        foreach ($this->getChildren($parent) as $currentChild) {
            if ($currentChild->getId() == $child->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this $child a child of $parent?
     *
     * Just calls self::isParentOf($parent, $child)
     * @see self::isParentOf()
     *
     * @param \Schoolbox\User $parent
     * @param \Schoolbox\User $child
     * @return bool
     */
    public function isChildOf(\Schoolbox\User $parent, \Schoolbox\User $child): bool
    {
        return $this->isParentOf($parent, $child);
    }
}
