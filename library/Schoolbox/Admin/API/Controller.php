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
use League\Flysystem\FilesystemException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\UploadedFileInterface;
use Schoolbox\Admin\Import\Profile\Importer;
use Schoolbox\Admin\Import\Profile\Mapping;
use Schoolbox\External;
use Schoolbox\Import\ImporterFactory;
use Schoolbox\Resource\Storage;
use Schoolbox\Mapper;
use SensitiveParameter;

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
        Mapping::CSV_STUDENT,       // 'student'
        Mapping::CSV_PARENT,        // 'parent'
        Mapping::CSV_STAFF,         // 'staff'
        Mapping::CSV_RELATIONSHIP   // 'relationship'
    ];


    /**
     * Timetable and Enrolment CSVs
     */
    private const REQUIRED_TIMETABLE_CSVS = [
        'timetable-structure'   => ImporterFactory::IMPORT_STRUCTURE,
        'timetable'             => ImporterFactory::IMPORT_TIMETABLE,
        'enrolment'             => ImporterFactory::IMPORT_ENROLMENT,
    ];


    /** @var Storage\Mapper */
    private Storage\Mapper $storageMapper;


    /** @var ImporterFactory */
    private ImporterFactory $importerFactory;


    /**
     * @var External\Profile\Mapper
     */
    private External\Profile\Mapper $baseProfileMapper;


    /**
     * @var External\Profile\Parent\Mapper
     */
    private External\Profile\Parent\Mapper $parentProfileMapper;


    /**
     * @var External\Profile\Student\Mapper
     */
    private External\Profile\Student\Mapper $studentProfileMapper;


    /**
     * @var External\Profile\Staff\Mapper
     */
    private External\Profile\Staff\Mapper $staffProfileMapper;


    /**
     * @var External\Profile\Relationship\Mapper
     */
    private External\Profile\Relationship\Mapper $relationshipMapper;


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
    public function postUploadCsv(): void
    {
        $post = $this->requestPost;

        $responseJson = [
            'success' => false
        ];

        $statusCode = null;
        try {
            // regrettably we can't do these checks in the module, because we don't have access to the $_POST array
            $this->authenticate((string)$post->get('key'));

            // key validated; you may proceed
            /** @var UploadedFileInterface|null $file */
            $file = $this->getRequest()->getUploadedFiles()['file'] ?? null;

            if (!$file) {
                throw new InvalidArgumentException('No CSV was received by the server.', 400);
            }

            try {
                $storage = $this->storageMapper->acceptStream(
                    $file->getClientFilename(),
                    $file->getStream()->getContents(),
                    false,
                );
            } catch (Mapper\Exception $e) {
                throw new InvalidArgumentException('The CSV was rejected by the server.', 400);
            }

            $responseJson['success'] = true;
            $responseJson['file'] = $storage->getHash();
        } catch (InvalidArgumentException $e) {
            $responseJson['error'] = $e->getMessage();
            $statusCode = $e->getCode();
        }

        $this->response = $this->responseAsJson($responseJson);
        if (isset($statusCode)) {
            $this->response = $this->response->withStatus($statusCode);
        }
    }


    /**
     *
     * @throws FilesystemException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @api POST /api/admin/process
     *
     */
    public function postProcess(): void
    {
        $this->extendExecutionTime(15 * 60);

        $post         = $this->requestPost;
        $errors       = [];
        $responseJson = ['success' => false];
        $statusCode   = 200;

        try {
            // regrettably we can't do these checks in the module, because we don't have access to the $_POST array
            $this->authenticate((string)$post->get('key'));

            // key validated; you may proceed
            // check for whether all the necessary files are supplied, and valid
            $files = [];

            /* Consume each csv files that we know to be required
             * based on inspecting what the user sent
             */
            $expectedKeys = array_merge(self::REQUIRED_USER_PROFILE_CSVS, array_keys(self::REQUIRED_TIMETABLE_CSVS));

            foreach ($expectedKeys as $requiredCsv) {
                $hash = $post->get($requiredCsv);

                /* No hash provided; user didn't supply it.
                 * Skip it quietly
                 */
                if (!$hash) {
                    continue;
                }

                /* A value was provided; see if it exists in storage
                 */
                try {
                    if (!in_array($requiredCsv, self::REQUIRED_USER_PROFILE_CSVS, true)) {
                        // assign the given hash to its place in the files array
                        $files[self::REQUIRED_TIMETABLE_CSVS[$requiredCsv]] = $this->storageMapper->findByHash($hash);
                    } else {
                        $files[$requiredCsv] = $this->storageMapper->findByHash($hash);
                    }
                } catch (Mapper\Exception $e) {
                    $errors[] = "Invalid file hash given for '$requiredCsv'.";
                    continue;
                }
            }

            /* User didn't supply any files in the parameters expected
             *     - either they literally didn't supply anything, or
             *     - the args are misspelled e.g. used structure=... instead of timetable-structure=...
             */
            if (!\count($files)) {
                $responseJson['error'] = 'No files supplied.';
                $statusCode = 400;
            } elseif (\count($errors)) {
                /* Some other error occurred during processing
                 */
                $responseJson['error'] = $errors;
                $statusCode = 400;
            } else {
                // valid files found and loaded, we're good to attempt processing

                /** @var IConnector $db */
                $db = $this->getDi()->get('databaseInternal');

                $write = fopen('php://memory', 'rb+');

                $log = new Logger($write, $write, \Monolog\Logger::WARNING, [], true);

                try {
                    foreach ($files as $keyspace => $file) {
                        if (!in_array($keyspace, self::REQUIRED_USER_PROFILE_CSVS, true)) {
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
                    $statusCode = 500;
                }
            }
        } catch (InvalidArgumentException $e) {
            $responseJson['error'] = $e->getMessage();
            $statusCode = $e->getCode();
        }

        $this->response = $this->responseAsJson($responseJson)->withStatus($statusCode);
    }


    /**
     * Decide if the given $key was valid and matches the config var
     * For some reason this throws exceptions
     *
     * @param string $key
     * @return void
     * @throws InvalidArgumentException
     */
    private function authenticate(#[SensitiveParameter] string $key): void
    {
        if (!$key) {
            throw new InvalidArgumentException('No authentication token was supplied.', 401);
        }

        if ($this->getConfigLoader()->get('api_key') !== $key) {
            throw new InvalidArgumentException('The given authentication token is invalid.', 403);
        }
    }
}
