<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy\Helper;

use External_ClassInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Helper class for locating students given a teacher user.
 */
class StudentLocator
{
    /**
     * Cache for student lookups.
     *
     * This is an expensive lookup (requiring two SIS calls), therefore it
     * should be cached.
     *
     * @var array<int, GenericCollection|User[]>
     */
    private array $resultCache = [];

    /**
     * Constructor
     *
     * @param UserMapper              $userMapper
     * @param Folder\Mapper           $folderMapper
     * @param External_ClassInterface $externalClass
     */
    public function __construct(
        private UserMapper $userMapper,
        private Folder\Mapper $folderMapper,
        private External_ClassInterface $externalClass,
    ) {
    }

    /**
     * Locate this teacher user's list of students.
     *
     * If this teacher's students are already fetched, return the previous
     * result and do not refetch.
     *
     * @param User $teacher
     * @return GenericCollection|User[] A list of students which `$teacher`
     *                                  teaches in any of their classes.
     */
    public function locateStudents(User $teacher): GenericCollection
    {
        $this->resultCache[$teacher->getId()] ??= $this->internalLocateStudents($teacher);
        return $this->resultCache[$teacher->getId()];
    }

    /**
     * Internal helper for locating a teacher user's list of students.
     *
     * @param User $teacher
     * @return GenericCollection|User[]
     */
    private function internalLocateStudents(User $teacher): GenericCollection
    {
        // Load the folder IDs for all given subjects
        $subjectFolderIds = $teacher->getSubjectCollection()
                                    ->filter(fn ($x) => !empty($x['id']))
                                    ->map(fn ($x) => $x['id']);
        if (count($subjectFolderIds) === 0) {
            return $this->userMapper->createCollection();
        }

        // Load the folders themselves
        // TODO is folder mapper even necessary? I have subject codes
        $folders = $this->folderMapper->findByColumn('id', $subjectFolderIds);
        if (count($folders) === 0) {
            return $this->userMapper->createCollection();
        }

        // Load the external students for this teacher
        $externalStudents = $this->externalClass->getStudents(
            implode(
                ',',
                $folders
                    ->map(fn (Folder\Model $x) => $x->getCodeString())
                    ->getAll()
            )
        );
        if (count($externalStudents) === 0) {
            return $this->userMapper->createCollection();
        }

        // Load the actual user objects
        return $this->userMapper->findByColumnList(
            [
                'synergy_id'   => array_column($externalStudents, 'synergy_id'),
                'g.group_type' => Role::STUDENT,
            ]
        );
    }
}
