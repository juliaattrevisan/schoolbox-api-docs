<?php

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
    private Collection $students;
    private Collection $teachers;
    private \External_User $externalUser;
    private \External_Class $externalClass;
    private UserMapper $userMapper;
    private string $externalType;

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
        $this->students = new Collection();
        $this->teachers = new Collection();
    }

    public function getParents(\Schoolbox\User $user)
    {
        //load students if they have not been loaded
        if (empty($this->parents) || empty($this->parents[$user->getExternalId()])) {
            $this->loadParents([$user]);
        }
        //return $this->parents
        return $this->parents[$user->getExternalId()];
    }

    public function getTeachers(\Schoolbox\User $user)
    {
        //load teachers if they have not been loaded
        if (empty($this->teachers) || empty($this->teachers[$user->getExternalId()])) {
            $this->loadTeachers([$user]);
        }
        //return teachers
        return $this->teachers[$user->getExternalId()];
    }

    public function getStudents(\Schoolbox\User $user)
    {
        //load students if they have not been loaded
        if (empty($this->students) || empty($this->students[$user->getExternalId()])) {
            $this->loadStudents([$user]);
        }
        //return students
        return $this->students[$user->getExternalId()];
    }

    /**
     * @param \Schoolbox\User[] $students
     * @return void
     */
    public function loadParents($students)
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
     * @param \Schoolbox\User[] $students
     * @return void
     */
    public function loadTeachers($students)
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
     * @param \Schoolbox\User[] $students
     * @return void
     */
    public function loadStudents($students)
    {
        foreach ($students as $student) {
            $this->students[$student->getExternalId()] = new GenericCollection(\Schoolbox\User::class);
            $enrolments                             = $this->externalUser->getEnrolledStudents($student->getExternalId());
            foreach ($enrolments as $child) {
                try {
                    $this->students[$student->getExternalId()]->add($this->userMapper->findByExternalId($child['RelatedID']));
                } catch (MapperException $e) {
                    // noop
                }
            }
        }
    }

    /**
     * Is this $parent a parent of $child?
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
        if (null !== $child->getParents()) {
            foreach ($child->getParents() as $parent) {
                if ($parent->getId() == $child->getId()) {
                    return true;
                }
            }
        }

        /* $this->children was never initialised for this user
         * Their descendents are inconclusive; continue
         */
        foreach ($this->getStudents($parent) as $currentChild) {
            if ($currentChild->getId() == $child->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this $child a child of $parent?
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
