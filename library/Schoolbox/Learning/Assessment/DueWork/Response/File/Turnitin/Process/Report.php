<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin\Process;

use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin;

/**
 * The second half of a Turnitin request
 *
 * This generates the report when Turnitin finishes the upload
 * Tells us when the report is ready via a webhook
 */
class Report
{

    /**
     * @var Turnitin\Api
     */
    private Turnitin\Api $api;


    /**
     * @var Turnitin\Mapper
     */
    private Turnitin\Mapper $turnitinMapper;


    /**
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $log;


    /**
     * Constructor.
     *
     * @param Turnitin\Api         $api
     * @param Turnitin\Mapper      $turnitinMapper
     * @param LoggerInterface|null $log
     */
    public function __construct(
        Turnitin\Api $api,
        Turnitin\Mapper $turnitinMapper,
        ?LoggerInterface $log = null
    ) {
        $this->api = $api;
        $this->turnitinMapper = $turnitinMapper;
        $this->log = $log;
    }


    /**
     * For the given Turnitin instance, go fetch its report info
     * and remember some of the results for performance reasons
     *
     * @param Turnitin\Model $model
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function process(Turnitin\Model $model): void
    {
        try {
            $report = $this->getSimilarityReport($model);
        } catch (Turnitin\Exception\BadEndpoint | Turnitin\Exception\Credentials | \DomainException $e) {
            $this->turnitinMapper->save($model
            ->setErrorCode((string)$e->getCode())
            ->setErrorMessage($e->getMessage()));
        }
    }


    /**
     * Get a turnitin report
     *
     * @param Turnitin\Model $model
     * @return array
     * @throws Turnitin\Exception\BadEndpoint
     * @throws Turnitin\Exception\Credentials
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    private function getSimilarityReport(Turnitin\Model $model): array
    {
        $this->logState(sprintf(
            'Report %s: requesting similarity report from turnitin...',
            $model->getId()
        ));

        return $this->api->retrieve('GET', "/api/v1/submissions/{$model->getTurnitinSubmissionId()}/similarity", []);
    }


    /**
     * Log to Logger if one was provided (used in cli tools)
     *
     * @param string $message
     */
    private function logState(string $message): void
    {
        if (null !== $this->log) {
            $this->log->debug($message);
        }
    }
}
