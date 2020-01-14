<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Admin\Import\Profile\OrphanRemover;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;

/**
 * Class Cleanup
 *
 * Move processed kamar files into an archive folder
 *
 * This allows us to keep track of source files that populated user/timetable data until it's okay
 * to remove them permanently; otherwise we don't leave ourselves a debugging opportunity
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint
 */
class Cleanup implements IStrategy
{

    /**
     * Archived xmls go here
     */
    private const KAMAR_ARCHIVE_DIR = 'archive';


    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * @var bool
     */
    private $perform;


    /**
     * @var string
     */
    private $cacheDir;


    /**
     * @var string
     */
    private $archiveDir;


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
     * @param string                          $streamDir
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     */
    public function __construct(
        ?LoggerInterface $log,
        bool $perform,
        string $streamDir,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->cacheDir = $streamDir . '/' . Setup::KAMAR_CACHE_DIR;
        $this->archiveDir = $this->cacheDir . '/' . self::KAMAR_ARCHIVE_DIR;

        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;
    }


    /**
     * Move all the files into a subfolder
     *
     * @throws RuntimeException
     */
    public function import(): void
    {

        /* Only move files around if we're performing
         */
        if ($this->perform) {

            /* Create an archive dir
             */
            if (!is_dir($this->archiveDir)) {
                if ($this->log) {
                    $this->log->info(sprintf(
                        'Creating new kamar xml archive dir %s',
                        $this->archiveDir
                    ));
                }

                /* storm autocompleted this suggestion for me
                 */
                if (!mkdir($concurrentDirectory = $this->archiveDir) && !is_dir($concurrentDirectory)) {
                    $message = sprintf('Directory "%s" was not created', $concurrentDirectory);

                    if ($this->log) {
                        $this->log->critical($message);
                    }

                    throw new RuntimeException($message);
                }
            }

            /* Remove every existing file from the archive
             */
            $dirhandle = opendir($this->archiveDir);

            while ($xmlFile = readdir($dirhandle)) {
                if ($xmlFile !== '.' && $xmlFile !== '..') {
                    $this->log->info(sprintf(
                        'Removing kamar xml %s from archive',
                        $xmlFile
                    ));

                    $source = "{$this->archiveDir}/{$xmlFile}";

                    unlink($source);
                }
            }

            /* Move every kamar file into the archive
             */
            $dirhandle = opendir($this->cacheDir);

            while ($xmlFile = readdir($dirhandle)) {
                if ($xmlFile !== '.' && $xmlFile !== '..' && $xmlFile !== self::KAMAR_ARCHIVE_DIR) {
                    $this->log->info(sprintf(
                        'Archiving kamar xml %s into %s',
                        $xmlFile,
                        $this->archiveDir
                    ));

                    $source = "{$this->cacheDir}/{$xmlFile}";
                    $destination = "{$this->archiveDir}/" . pathInfo($source, PATHINFO_BASENAME);

                    rename($source, $destination);
                }
            }

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
