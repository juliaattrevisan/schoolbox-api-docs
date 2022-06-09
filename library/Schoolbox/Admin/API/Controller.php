<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\API;

use Alaress\Database\Connector\IConnector;
use Alaress\Lumberjack\Logger;
use Alaress\Web;
use InvalidArgumentException;
use Schoolbox\Admin\Import\Profile\Importer;
use Schoolbox\Admin\Import\Profile\Mapping;
use Schoolbox\External;
use Schoolbox\Import\ImporterFactory;
use Schoolbox\Resource\Storage;
use Schoolbox\Mapper;

/**
 * Class Controller
 *
 * @package Schoolbox\Admin\API
 */
class Controller extends Web\Controller\Base
{

    /**
     * Profile CSVs
     * Processor consts for declaring which file is being supplied
     */
    private const REQUIRED_USER_PROFILE_CSVS = [
        Mapping::CSV_STUDENT,
        Mapping::CSV_PARENT,
        Mapping::CSV_STAFF,
        Mapping::CSV_RELATIONSHIP
    ];


    /**
     * Timetable and Enrolment CSVs
     */
    private const REQUIRED_CSVS = [
        'timetable-structure'   => ImporterFactory::IMPORT_STRUCTURE,
        'timetable'             => ImporterFactory::IMPORT_TIMETABLE,
        'enrolment'             => ImporterFactory::IMPORT_ENROLMENT,
    ];


    /** @var Storage\Mapper */
    private $storageMapper;


    /** @var ImporterFactory */
    private $importerFactory;


    /**
     * @var External\Profile\Mapper
     */
    private $baseProfileMapper;


    /**
     * @var External\Profile\Parent\Mapper
     */
    private $parentProfileMapper;


    /**
     * @var External\Profile\Student\Mapper
     */
    private $studentProfileMapper;


    /**
     * @var External\Profile\Staff\Mapper
     */
    private $staffProfileMapper;


    /**
     * @var External\Profile\Relationship\Mapper
     */
    private $relationshipMapper;


    /**
     * Controller constructor.
     *
     * @param Storage\Mapper                       $storageMapper
     * @param ImporterFactory                      $importerFactory
     * @param External\Profile\Mapper              $baseMapper
     * @param External\Profile\Parent\Mapper       $parentMapper
     * @param External\Profile\Student\Mapper      $studentMapper
     * @param External\Profile\Staff\Mapper        $staffMapper
     * @param External\Profile\Relationship\Mapper $relationshipMapper
     * @param Storage\FilesystemProxy              $filesystemProxy
     */
    public function __construct(
        Storage\Mapper $storageMapper,
        ImporterFactory $importerFactory,
        External\Profile\Mapper $baseMapper,
        External\Profile\Parent\Mapper $parentMapper,
        External\Profile\Student\Mapper $studentMapper,
        External\Profile\Staff\Mapper $staffMapper,
        External\Profile\Relationship\Mapper $relationshipMapper,
        private Storage\FilesystemProxy $filesystemProxy,
    ) {
        $this->storageMapper = $storageMapper;
        $this->importerFactory = $importerFactory;
        $this->baseProfileMapper = $baseMapper;
        $this->parentProfileMapper = $parentMapper;
        $this->studentProfileMapper = $studentMapper;
        $this->staffProfileMapper = $staffMapper;
        $this->relationshipMapper = $relationshipMapper;
    }


    /**
     * @api POST /api/admin/csv
     */
    public function postUploadCsv()
    {
        $responseJson = [
            'success' => false
        ];

        $rs = $this->getResponse();

        // regrettably we can't do these checks in the module, because we don't have access to the $_POST array

        $key  = $this->getRequest()->post->get('key');

        try {
            if (!$key) {
                throw new InvalidArgumentException('No authentication token was supplied.', 401);
            }

            if ($this->getConfigLoader()->get('api_key') !== $key) {
                throw new InvalidArgumentException('The given authentication token is invalid.', 403);
            }

            // key validated; you may proceed

            $file = $this->getRequest()->files->get('file');

            if (!$file) {
                throw new InvalidArgumentException('No CSV was received by the server.', 400);
            }

            try {
                $storage = $this->storageMapper->accept($file['name'], $file['tmp_name'], false);
            } catch (Mapper\Exception $e) {
                throw new InvalidArgumentException('The CSV was rejected by the server.', 400);
            }

            $responseJson['success'] = true;
            $responseJson['file'] = $storage->getHash();
        } catch (InvalidArgumentException $e) {
            $responseJson['error'] = $e->getMessage();
            $rs->status->setCode($e->getCode());
        }

        $rs->content->set($this->buildJsonOutput($responseJson));
    }


    /**
     *
     * @api POST /api/admin/process
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Aura\Web\Exception\InvalidStatusCode
     */
    public function postProcess()
    {
        $post = $this->getRequest()->post;
        $rs   = $this->getResponse();

        $responseJson = [
            'success' => false
        ];

        // regrettably we can't do these checks in the module, because we don't have access to the $_POST array

        $key  = $post->get('key');

        try {
            if (!$key) {
                throw new InvalidArgumentException('No authentication token was supplied.', 401);
            }

            if ($this->getConfigLoader()->get('api_key') !== $key) {
                throw new InvalidArgumentException('The given authentication token is invalid.', 403);
            }

            // key validated; you may proceed

            // check for whether all the necessary files are supplied, and valid

            $files = [];

            foreach (array_merge(self::REQUIRED_USER_PROFILE_CSVS, array_keys(self::REQUIRED_CSVS)) as $requiredCsv) {
                $hash = $post->get($requiredCsv);

                if (!$hash) {
                    $errors[] = "No file hash was given for '$requiredCsv'.";
                    continue;
                }

                try {
                    if (!in_array($requiredCsv, self::REQUIRED_USER_PROFILE_CSVS)) {
                        // assign the given hash to its place in the files array
                        $files[self::REQUIRED_CSVS[$requiredCsv]] = $this->storageMapper->findByHash($hash);
                    } else {
                        $files[$requiredCsv] = $this->storageMapper->findByHash($hash);
                    }
                } catch (Mapper\Exception $e) {
                    $errors[] = "Invalid file hash given for '$requiredCsv'.";
                    continue;
                }
            }

            if (isset($errors)) {
                $responseJson['error'] = $errors;
                $rs->status->set(400);
            } else {
                // valid files found and loaded, we're good to attempt processing

                /** @var IConnector $db */
                $db = $this->getDi()->get('databaseInternal');

                $write = fopen('php://memory', 'rb+');

                $log = new Logger($write, $write, \Monolog\Logger::WARNING, [], true);

                try {
                    foreach ($files as $keyspace => $file) {
                        if (!in_array($keyspace, self::REQUIRED_USER_PROFILE_CSVS)) {
                            // this is where the action happens
                            $this->importerFactory->buildImporter(
                                $keyspace,
                                $this->importerFactory->buildReader($keyspace, $this->filesystemProxy->readStream($file)),
                                $log,
                                true
                            )->save();
                        } else {
                            (new Importer(
                                $keyspace,
                                $file,
                                $db,
                                $this->baseProfileMapper,
                                $this->parentProfileMapper,
                                $this->studentProfileMapper,
                                $this->staffProfileMapper,
                                $this->relationshipMapper,
                                $log,
                                $this->filesystemProxy,
                            ))->setPerform(true)->process();
                        }
                    }

                    $responseJson['success'] = true;

                    rewind($write);

                    $responseJson['summary'] = array_filter(explode("\n", trim(stream_get_contents($write))));
                } catch (\Exception $e) {
                    // one of those times where you really need to catch EVERYTHING
                    $responseJson['error'] = $e->getMessage();
                    $rs->status->setCode(500);
                }
            }
        } catch (InvalidArgumentException $e) {
            $responseJson['error'] = $e->getMessage();
            $rs->status->setCode($e->getCode());
        }

        $rs->content->set($this->buildJsonOutput($responseJson));
    }
}
