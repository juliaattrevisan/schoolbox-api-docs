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
        $similarity = $this->generateReport($model);

//
//        if (null !== $similarity) {
//            $report = $this->getSimilarityReport($model);
//        }
//
//        try {
////            $report = $this->getSimilarityReport($model);
//        } catch (Turnitin\Exception\BadEndpoint | Turnitin\Exception\Credentials | \DomainException $e) {
//            $this->turnitinMapper->save($model
//            ->setErrorCode((string)$e->getCode())
//            ->setErrorMessage($e->getMessage()));
//        }
    }

    /**
     * Request turnitin to start manufacturing a Similarity Report for $model
     * This is the last step in this phase, since turnitin normally uses a webhook
     * to tell us when it's done with this manufacturing.
     *
     * @param Turnitin\Model   $model
     * @return array|null
     * @throws Turnitin\Exception\BadEndpoint
     * @throws Turnitin\Exception\Credentials
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */
    private function generateReport(Turnitin\Model $model): ?array
    {
        /* Log the state change
         */
        $this->logDebug(sprintf(
            'Report %s: requesting Similarity Report for Turnitin submission %s',
            $model->getId(),
            $model->getTurnitinSubmissionId()
        ));

        /* These use the default search repositories for now
         * TODO We can refine these search repos later
         */
        $payload = [
            'generation_settings' => [
                'search_repositories' => [
                    'INTERNET',
                    'SUBMITTED_WORK',
                    'PUBLICATION',
                    'CROSSREF',
                    'CROSSREF_POSTED_CONTENT'
                ],
            ]
        ];

        $result = $this->api->retrieve('PUT', "/api/v1/submissions/{$model->getTurnitinSubmissionId()}/similarity", $payload);

        /* http 202 equates to this, which means you have to wait:
            Array
            (
                [message] => Successfully scheduled similarity report generation
            )
        */
        $this->logDebug(sprintf(
            'Report %s: Turnitin submission id %s - %s',
            $model->getId(),
            $model->getTurnitinSubmissionId(),
            $result['message']
        ));

        return $result;
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
        $this->logDebug(sprintf(
            'Report %s: requesting similarity report from turnitin...',
            $model->getId()
        ));

        return $this->api->retrieve('GET', "/api/v1/submissions/{$model->getTurnitinSubmissionId()}", []);
    }


    /**
     * Log to Logger if one was provided (used in cli tools)
     *
     * @param string $message
     */
    private function logDebug(string $message): void
    {
        if (null !== $this->log) {
            $this->log->debug($message);
        }
    }


    /**
     * Specifically log a warning to the cli
     *
     * @param string $message
     */
    private function logWarning(string $message): void
    {
        if (null !== $this->log) {
            $this->log->warning($message);
        }
    }
}
