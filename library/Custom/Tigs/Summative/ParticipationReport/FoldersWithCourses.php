<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\ParticipationReport;

use Schoolbox\Resource\Folder;

/**
 * Class FoldersWithCourses
 *
 * Get a list of folder ids that have an associated Course Implementation.
 * This is used in conjunction with other folder filtration mechanisms such as
 * PreviousClassIdCache and the blacklist exclusions to limit the folders whose
 * feedbacks we must inspect.
 *
 * As time rolls onwards we find more and more reasons to exclude folders
 *
 * @package Custom\Tigs\Summative\ParticipationReport
 */
class FoldersWithCourses
{

    /**
     * @var Folder\Mapper
     */
    private Folder\Mapper $folderMapper;


    /**
     * A list of folder ids that at student was in, had a published feedback,
     * but is no longer in
     *
     * @var array
     */
    private array $foldersWithCourses = [];


    /**
     * Constructor.
     *
     * @param Folder\Mapper $folderMapper
     */
    public function __construct(Folder\Mapper $folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }


    /**
     * Find a list of folder ids which have an associated course implementation
     *
     * @return array
     */
    public function find(): array
    {
        $statement = $this->folderMapper->getDatabase()->query('
            SELECT DISTINCT ci.folder_id
            FROM folder f
            INNER JOIN `course_implementation` ci ON f.id = ci.folder_id
            INNER JOIN course_unit_implementation cui ON ci.id = cui.course_implementation_id
            INNER JOIN assessment_course_unit_implementation acui ON cui.id = acui.course_unit_implementation_id
            WHERE f.deleted_at IS NULL
            AND ci.deleted_at IS NULL
            AND cui.deleted_at IS NULL
            ORDER BY folder_id
        ');

        $stack = [];

        foreach ($statement as $row) {
            $stack[$row['folder_id']] = $row['folder_id'];
        }

        /* Give this as output, but also remember it, because we'll need it later
         */
        $this->foldersWithCourses = $stack;

        return $this->foldersWithCourses;
    }


    /**
     * @return array
     */
    public function getIds(): array
    {
        return $this->foldersWithCourses;
    }
}
