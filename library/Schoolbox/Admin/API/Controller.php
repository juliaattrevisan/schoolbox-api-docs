<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\API;

use Alaress\Lumberjack\Logger;
use Alaress\Web;
use InvalidArgumentException;
use Schoolbox\Import\ImporterFactory;
use Schoolbox\Resource\Storage;
use Schoolbox\Mapper;


class Controller extends Web\Controller\Base
{
    private const REQUIRED_CSVS = [
        'user'                  => ImporterFactory::IMPORT_USER,
        'timetable-structure'   => ImporterFactory::IMPORT_STRUCTURE,
        'timetable'             => ImporterFactory::IMPORT_TIMETABLE,
        'enrolment'             => ImporterFactory::IMPORT_ENROLMENT,
    ];

    /** @var Storage\Mapper */
    private $storageMapper;

    /** @var ImporterFactory */
    private $importerFactory;


    /**
     * @param Storage\Mapper  $storageMapper
     * @param ImporterFactory $importerFactory
     */
    public function __construct(Storage\Mapper $storageMapper, ImporterFactory $importerFactory)
    {
        $this->storageMapper   = $storageMapper;
        $this->importerFactory = $importerFactory;
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
     * @api POST /api/post/process
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

            foreach (array_keys(self::REQUIRED_CSVS) as $requiredCsv) {

                $hash = $post->get($requiredCsv);

                if (!$hash) {
                    $errors[] = "No file hash was given for '$requiredCsv'.";
                    continue;
                }

                try {
                    // assign the given hash to its place in the files array
                    $files[self::REQUIRED_CSVS[$requiredCsv]] = $this->storageMapper->findByHash($hash);

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

                $write = fopen('php://memory', 'rb+');

                $log = new Logger($write, $write, \Monolog\Logger::WARNING, [], true);

                try {
                    foreach ($files as $keyspace => $file) {
                        // this is where the action happens
                        $this->importerFactory->buildImporter($keyspace, $this->importerFactory->buildReader($keyspace, $file), $log, true)->save();
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