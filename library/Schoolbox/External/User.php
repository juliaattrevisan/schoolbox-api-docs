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

    public function getParents(string $externalId)
    {
        //load students if they have not been loaded
        if (empty($this->parents)) {
            $this->loadParents($externalId);
        }
        //return $this->parents
        return $this->parents;
    }

    public function getTeachers(string $externalId)
    {
        //load teachers if they have not been loaded
        if (empty($this->teachers)) {
            $this->loadTeachers($externalId);
        }
        //return teachers
        return $this->teachers;
    }

    public function getStudents(string $externalId)
    {
        //load students if they have not been loaded
        if (empty($this->students)) {
            $this->loadStudents($externalId);
        }
        //return students
        return $this->students;
    }

    private function loadParents(string $externalId)
    {
        $allParents               = $this->externalUser->getStudentContactList([$externalId]);

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

    private function loadTeachers(string $externalId)
    {
        $codeMatcher       = (new ClassCodeMatchFactory())->build($this->externalType);

        // Get all of the students subjects

        $found = [];
        //TODO: alter getSubjectList to accept just the externalID, not the userObject....???
        $subjects = $this->externalUser->getSubjectList($this);

        // Iterate and find teachers associated with each subject
        if (is_array($subjects)) {
            foreach ($subjects as $subject) {
                $teachers = $this->externalClass->getTeachers($codeMatcher::generateFolderCode($subject['campusCode'], $subject['code']));

                foreach ($teachers as $teacher) {
                    // If we haven't already, add this teacher to their collection

                    if (!isset($found[$teacher])) {
                        try {
                            $this->teachers->add(
                                UserMapper::getInstance()->findByExternalId($teacher, Role::STAFF)
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

    private function loadStudents(string $externalId)
    {
        $this->students = new GenericCollection(self::class);
        $enrolments = $this->externalUser->getEnrolledStudents($externalId);
        foreach ($enrolments as $child) {
            try {
                $this->students->add($this->userMapper->findByExternalId($child['RelatedID']));
            } catch (MapperException $e) {
                // noop
            }
        }
    }
}
