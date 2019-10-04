<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;

use Schoolbox\External\Profile;

/**
 * Class OrphanRemover
 *
 * Remove any base profiles that are no longer connected to an extension profile
 * student, staff or parent
 *
 * @package Schoolbox\Admin\Import\Profile
 */
class OrphanRemover
{
    /**
     * @var Profile\Mapper
     */
    private $baseProfileMapper;


    /**
     * @var Profile\Parent\Mapper
     */
    private $parentProfileMapper;


    /**
     * @var Profile\Student\Mapper
     */
    private $studentProfileMapper;


    /**
     * @var Profile\Staff\Mapper
     */
    private $staffProfileMapper;


    /**
     * @var Profile\Relation\Mapper
     */
    private $relationMapper;


    /**
     * OrphanRemover constructor.
     *
     * @param Profile\Mapper          $baseProfileMapper
     * @param Profile\Student\Mapper  $studentProfileMapper
     * @param Profile\Parent\Mapper   $parentProfileMapper
     * @param Profile\Staff\Mapper    $staffProfileMapper
     * @param Profile\Relation\Mapper $relationMapper
     */
    public function __construct(
        Profile\Mapper $baseProfileMapper,
        Profile\Student\Mapper $studentProfileMapper,
        Profile\Parent\Mapper $parentProfileMapper,
        Profile\Staff\Mapper $staffProfileMapper,
        Profile\Relation\Mapper $relationMapper
    ) {
        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationMapper = $relationMapper;
    }

    /**
     * Remove any base profiles that are no longer connected to an extension profile
     * student, staff or parent
     *
     * @return int
     */
    public function remove(): int
    {
        $statement = $this->baseProfileMapper->getDatabase()->query("
            SELECT GROUP_CONCAT(profile.id) AS orphans
            FROM {$this->baseProfileMapper->getTable()} profile
            LEFT JOIN {$this->studentProfileMapper->getTable()} student ON profile.id = student.sis_user_profile_id
            LEFT JOIN {$this->staffProfileMapper->getTable()} staff ON profile.id = staff.sis_user_profile_id
            LEFT JOIN {$this->parentProfileMapper->getTable()} parent ON profile.id = parent.sis_user_profile_id
            WHERE student.sis_user_profile_id IS NULL
            AND staff.sis_user_profile_id IS NULL
            AND parent.sis_user_profile_id IS NULL
        ");

        $orphans = $statement->fetch(\PDO::FETCH_ASSOC)['orphans'];

        if ($orphans) {

            /* Remove orphaned profiles' relations
             *
             * Sometimes data is broken such that there are relations between base profiles
             * but they don't have any extensions in student, parent or staff tables
             */
            $this->baseProfileMapper->getDatabase()->query("
                DELETE FROM {$this->relationMapper->getTable()}
                WHERE child_profile_id IN ({$orphans})
                OR user_profile_id IN ({$orphans})
            ");

            /* Remove orphaned profiles
             */
            $this->baseProfileMapper->getDatabase()->query("
                DELETE FROM {$this->baseProfileMapper->getTable()}
                WHERE {$this->baseProfileMapper->getIdentifierColumn()} IN ({$orphans})
            ");

            return substr_count($orphans, ',') + 1;
        }

        return 0;
    }
}
