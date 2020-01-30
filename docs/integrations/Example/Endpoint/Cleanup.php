<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Example\Base\Endpoint;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Admin\Import\Profile\OrphanRemover;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;

/**
 * Class Cleanup
 *
 * Remove any stale profiles
 *
 * @package Schoolbox\External\Connector\Example\Base\Endpoint
 */
class Cleanup implements IStrategy
{


    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * @var bool
     */
    private $perform;


    /**
     * @var UserProfile\Mapper
     */
    private $baseProfileMapper;


    /**
     * @var UserProfile\Student\Mapper
     */
    private $studentProfileMapper;


    /**
     * @var UserProfile\Parent\Mapper
     */
    private $parentProfileMapper;


    /**
     * @var UserProfile\Staff\Mapper
     */
    private $staffProfileMapper;


    /**
     * @var UserProfile\Relationship\Mapper
     */
    private $relationshipMapper;


    /**
     * Cleanup constructor.
     *
     * @param LoggerInterface|null            $log
     * @param bool                            $perform
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     */
    public function __construct(
        ?LoggerInterface $log,
        bool $perform,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;

        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;
    }


    /**
     * Remove stale profiles
     */
    public function import(): void
    {

        /* Only remove profiles if we're performing
         */
        if ($this->perform) {

            /* Remove everything that wasn't touched
             */
            $this->studentProfileMapper->getDatabase()->query("DELETE FROM {$this->studentProfileMapper->getTable()} WHERE updated_at = '0000-00-00 00:00:00' OR updated_at IS NULL");
            $this->parentProfileMapper->getDatabase()->query("DELETE FROM {$this->parentProfileMapper->getTable()} WHERE updated_at = '0000-00-00 00:00:00' OR updated_at IS NULL");
            $this->staffProfileMapper->getDatabase()->query("DELETE FROM {$this->staffProfileMapper->getTable()} WHERE updated_at = '0000-00-00 00:00:00' OR updated_at IS NULL");
            $this->baseProfileMapper->getDatabase()->query("DELETE FROM {$this->baseProfileMapper->getTable()} WHERE updated_at = '0000-00-00 00:00:00' OR updated_at IS NULL");

            /* Remove any orphaned profiles (base profiles that have no extension)
             */
            $orphans = (new OrphanRemover(
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper,
                $this->relationshipMapper
            ))->remove();

            $this->log->info("Removed {$orphans} orphaned profiles.");
        }
    }
}
