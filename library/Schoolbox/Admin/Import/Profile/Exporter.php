<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile;

use Alaress\Database\Connector\IConnector;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Profile;

/**
 * Class Exporter
 *
 * Export data currently in sis_user_profile_* into the requested CSV format
 *
 * The way it comes out should be structured identically to the way it went in,
 * for the sake of familiarity for end users
 *
 * @package Schoolbox\Admin\Import\Profile
 */
class Exporter
{

    /**
     * @var Mapping
     */
    private $mapping;


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
     * @var IConnector
     */
    private $database;


    /**
     * An array of log messages
     *
     * @var array
     */
    private $log = [];


    /**
     * Exporter constructor.
     *
     * @param string               $type
     * @param IConnector           $database
     * @param LoggerInterface|null $logger
     * @throws \InvalidArgumentException
     */
    public function __construct(string $type, IConnector $database, LoggerInterface $logger = null)
    {
        $this->mapping = new Mapping($type);
        $this->baseProfileMapper = new Profile\Mapper();
        $this->parentProfileMapper = new Profile\Parent\Mapper();
        $this->studentProfileMapper = new Profile\Student\Mapper();
        $this->staffProfileMapper = new Profile\Staff\Mapper();
        $this->relationMapper = new Profile\Relation\Mapper();
        $this->database = $database;
        $this->logger = $logger;

        $this->type = $type;
    }


    /**
     * Export based on the $type specified
     *
     * @throws \InvalidArgumentException
     */
    public function process(): void
    {
        // header()

        $this->processOne();
    }


    /**
     * Export one of the csv types
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function processOne(): void
    {
        if ($this->mapping->isStudentProfile($this->type)) {
            $this->exportStudentProfile();
        } elseif ($this->mapping->isStaffProfile($this->type)) {
            $this->exportStaffProfile();
        } elseif ($this->mapping->isParentProfile($this->type)) {
            $this->exportParentProfile();
        } elseif ($this->mapping->isRelation($this->type)) {
            $this->exportRelation();
        }
    }


    public function exportStudentProfile()
    {
        $profiles = $this->studentProfileMapper->findAll();

        foreach ($profiles as $profile) {
            new \Alaress_Probe($profile);
        }
    }

    public function exportStaffProfile()
    {
    }

    public function exportParentProfile()
    {
    }

    public function exportRelation()
    {
    }
}
