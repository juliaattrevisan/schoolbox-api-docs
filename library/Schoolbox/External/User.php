<?php

namespace Schoolbox\External;

use Schoolbox\Collection;
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

    public function __construct(
        private \External_User $externalUser,
        private \External_Class $externalClass,
        private UserMapper $userMapper,
        private string $externalType,
    ) {
        $this->parents = new Collection();
        $this->students = new Collection();
        $this->teachers = new Collection();
    }

    public function getParents(\Schoolbox\User $user)
    {
        //load students if they have not been loaded
        if (empty($this->parents)) {
            $this->loadParents($user);
        }
        //return $this->parents
        return $this->parents;
    }

    public function getTeachers(\Schoolbox\User $user)
    {
        //load teachers if they have not been loaded
        if (empty($this->teachers)) {
            $this->loadTeachers($user);
        }
        //return teachers
        return $this->teachers;
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

    private function loadParents(\Schoolbox\User $user)
    {
        $allParents               = $this->externalUser->getStudentContactList([$user->getExternalId()]);

        foreach ($allParents as $currentParent) {
            /* Add parent
             */
            if (!empty($currentParent['StudentContactID'])) {
                try {
                    $parent = $this->userMapper->findByExternalId($currentParent['StudentContactID'], Role::PARENT);
                    $this->parents->add($parent);
                } catch (MapperException $e) {
                    //noop
                }
            }
            /* Add speece
             */
            if (!empty($currentParent['StudentContactSpouseID'])) {
                try {
                    $spouse = $this->userMapper->findByExternalId($currentParent['StudentContactSpouseID'], Role::PARENT);
                    $this->parents->add($spouse);
                } catch (MapperException $e) {
                    //noop
                }
            }
        }
    }

    private function loadTeachers(\Schoolbox\User $user)
    {
        $codeMatcher       = (new ClassCodeMatchFactory())->build($this->externalType);

        // Get all of the students subjects

        $found = [];
        $subjects = $this->externalUser->getSubjectList($user);

        // Iterate and find teachers associated with each subject
        if (is_array($subjects)) {
            foreach ($subjects as $subject) {
                $teachers = $this->externalClass->getTeachers($codeMatcher::generateFolderCode($subject['campusCode'], $subject['code']));

                foreach ($teachers as $teacher) {
                    // If we haven't already, add this teacher to their collection

                    if (!isset($found[$teacher])) {
                        try {
                            $this->teachers->add(
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
            $this->students[$student->getExternalId()] = new GenericCollection(self::class);
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
}
