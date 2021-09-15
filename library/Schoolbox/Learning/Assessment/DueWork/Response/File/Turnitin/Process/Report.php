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
     * Status string from turnitin once it's finished processing our upload
     */
    private const UPLOAD_COMPLETE = 'COMPLETE';

    /**
     * Status string from turnitin if there was something wrong with our upload
     */
    private const UPLOAD_ERROR = 'ERROR';


    /**
     * Status string from turnitin once it's finished a report for us
     */
    private const REPORT_COMPLETE = 'COMPLETE';


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
            $reportInfo = $this->getReportInfo($model);

            /* If we got report data, cache some of it locally
             * then clear away all previous errors I suppose,
             * since they no longer have meaning...but is this wise?
             */
            if (null !== $reportInfo) {
                $model->setOverallMatchPercentage((float)$reportInfo['overall_match_percentage'])
                    ->setStatus(Turnitin\Model::STATUS_COMPLETE)
                    ->setErrorCode(null)
                    ->setErrorMessage(null);

                $this->turnitinMapper->save($model);
            }
        } catch (Turnitin\Exception\BadEndpoint | Turnitin\Exception\Credentials | \DomainException $e) {
            $this->turnitinMapper->save($model
            ->setErrorCode((string)$e->getCode())
            ->setErrorMessage($e->getMessage()));
        }
    }


    /**
     * Decide if the file upload finished; if it's COMPLETE
     * then I guess we're ready to move onto the next step which is to request a report generation
     *
     * @param Turnitin\Model $model
     * @return array|null
     * @throws Turnitin\Exception\BadEndpoint
     * @throws Turnitin\Exception\Credentials
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    private function getReportInfo(Turnitin\Model $model): ?array
    {
        /* Find out if this upload finished processing in turnitin town
         */
        $uploadResult = $this->api->retrieve('GET', "/api/v1/submissions/{$model->getTurnitinSubmissionId()}", []);

        if (null !== $uploadResult) {
            $this->logDebug(sprintf(
                'Report %s: upload complete; requesting report data',
                $model->getId()
            ));

            /* If the upload was complete, find out about the report itself
             * If it was previously requested, it might be ready now
             * Otherwise, come back later and ask again
             */
            if (self::UPLOAD_COMPLETE === $uploadResult['status']) {
                try {
                    $reportResult = $this->api->retrieve('GET', "/api/v1/submissions/{$model->getTurnitinSubmissionId()}/similarity", []);
                } catch (Turnitin\Exception\BadEndpoint $e) {
                    /* When the report hasn't been scheduled for generation, this endpoint returns 404
                     * imho that really should be InvalidArgument because the endpoint exists, but the
                     * remote server hasn't finished populating its contents yet
                     *
                     * Anyway, nothing to do here
                     */
                    $this->logDebug(sprintf(
                        'Report %s: report has not been scheduled for creation yet; doing so now',
                        $model->getId()
                    ));

                    $similarity = $this->generateReport($model);

                    return null;
                }

                /* Otherwise, the report was scheduled and something might be found here
                 */
                if (null !== $reportResult) {
                    /* If the report finished, just return its data
                     * for caching locally.
                     */
                    if (self::REPORT_COMPLETE === $reportResult['status']) {
                        $this->logDebug(sprintf(
                            'Report %s: generation completed. Overall Match Percentage is %s',
                            $model->getId(),
                            $reportResult['overall_match_percentage']
                        ));

                        return $reportResult;
                    }
                }
            } elseif (self::UPLOAD_ERROR === $uploadResult['status']) {
                $this->logWarning(sprintf(
                    'Report %s: upload contains error code %s',
                    $model->getId(),
                    $uploadResult['error_code']
                ));

                /* This can occur if your Online Submission html is too short, for example
                 * Save the error state
                 */
                $model->setErrorCode($uploadResult['error_code'])
                ->setStatus(Turnitin\Model::STATUS_ERROR);

                $this->turnitinMapper->save($model);
            }

//
//            $this->logDebug(sprintf(
//                'Report %s: report is not ready yet, but its upload status is %s',
//                $uploadResult['status'],
//                $model->getId()
//            ));

        }

        /* Nothing to do; report isn't ready for us yet
         */
        $this->logDebug(sprintf(
            'Report %s: report is not ready yet',
            $model->getId()
        ));

        return null;
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
     * @return array|null
     * @throws Turnitin\Exception\BadEndpoint
     * @throws Turnitin\Exception\Credentials
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    private function getSimilarityReport(Turnitin\Model $model): ?array
    {
        $this->logDebug(sprintf(
            'Report %s: requesting similarity report from turnitin...',
            $model->getId()
        ));

        $result = $this->api->retrieve('GET', "/api/v1/submissions/{$model->getTurnitinSubmissionId()}/similarity", []);

        $this->logDebug(sprintf(
            'Report %s: current turnitin internal status is %s',
            $model->getId(),
            $result['status']
        ));

        var_dump($result);

        /* $result['status'] is either PROCESSING or COMPLETE
         * Oddly, I'm seeing a report _start_ in COMPLETE which was confusing and misleading...
         */
        if (self::REPORT_COMPLETE === $result['status']) {
            return $result;
        }

        return null;
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
