<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\File\Process;

use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Learning\Turnitin\Api;
use Schoolbox\Learning\Turnitin\Exception\BadEndpoint;
use Schoolbox\Learning\Turnitin\Exception\Credentials;
use Schoolbox\Learning\Turnitin\LogTrait;

/**
 * The second half of a Turnitin request
 *
 * This generates the report when Turnitin finishes the upload
 * Tells us when the report is ready via a webhook
 */
class Report
{
    use LogTrait;


    /**
     * Choose a viewer permission set based on your role
     * Staff members get a different set than students or parents
     */
    public const VIEWER_PERMISSION_SET_STAFF = 'INSTRUCTOR';
    public const VIEWER_PERMISSION_SET_STUDENT = 'LEARNER';


    /**
     * Status string from turnitin once it's finished processing our upload
     */
    private const UPLOAD_COMPLETE = 'COMPLETE';


    /**
     * Status string from turnitin if they are still processing our upload
     */
    private const UPLOAD_PROCESSING = 'PROCESSING';


    /**
     * Status string from turnitin if there was something wrong with our upload
     */
    private const UPLOAD_ERROR = 'ERROR';


    /**
     * Status string from turnitin once it's finished a report for us
     */
    private const REPORT_COMPLETE = 'COMPLETE';


    /**
     * @var Api
     */
    private Api $api;


    /**
     * @var Turnitin\File\Mapper
     */
    private Turnitin\File\Mapper $turnitinMapper;


    /**
     * Constructor.
     *
     * @param Api                  $api
     * @param Turnitin\File\Mapper $turnitinMapper
     * @param LoggerInterface|null $log
     */
    public function __construct(
        Api $api,
        Turnitin\File\Mapper $turnitinMapper,
        ?LoggerInterface $log = null
    ) {
        $this->api = $api;
        $this->turnitinMapper = $turnitinMapper;
        $this->log = $log;
    }


    /**
     * For the given Turnitin instance, get its state from Loaded
     * to Prepared, which means we need to check when Turnitin finished
     * processing our upload before we can schedule a report generation
     *
     * @param Turnitin\File\Model $model
     * @return void
     * @throws Turnitin\Exception\Conflict
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function processLoadedReports(Turnitin\File\Model $model): void
    {
        try {
            $uploadInfo = $this->getUploadInfo($model);
        } catch (Turnitin\Exception\Exception $e) {
            $this->turnitinMapper->save($model
                ->setErrorCode((string)$e->getCode())
                ->setErrorMessage($e->getMessage()));
        }
    }


    /**
     * Schedule a prepared Turnitin report for actual creation
     * Upon finishing the creation, save the answers for later
     *
     * @param Turnitin\File\Model $model
     * @throws BadEndpoint
     * @throws Credentials
     * @throws Turnitin\Exception\Conflict
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function processPreparedReports(Turnitin\File\Model $model): void
    {
        /* Branch out here for historical documents, which require indexing only
         * https://developers.turnitin.com/docs/tca#index-a-submission
         * Everything else does generateReport, as usual, then ends.
         */
        if ($model->getHistorical()) {
            /* Historically added docs only get indexed; they don't get a report
             * We don't need to do as much as we normally would, such as caching
             * pieces of data locally etc.
             */
            $this->indexHistorical($model);
        } else {
            $reportInfo = $this->getReportInfo($model, null);

            /* If we got report data, cache some of it locally
             * then clear away all previous errors I suppose,
             * since they no longer have meaning...but is this wise?
             */
            if (null !== $reportInfo) {
                $this->cacheLocally($model, $reportInfo);
            }
        }
    }


    /**
     * Turnitin gave us the report details; save some locally
     * This is used by the cli processor and the webhooks too
     *
     * @param Turnitin\File\Model $model
     * @param array               $reportInfo
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function cacheLocally(Turnitin\File\Model $model, array $reportInfo): void
    {
        $this->changeState(
            $model,
            sprintf(
                'Report %s: generation completed. Overall Match Percentage is %s; setting status [%s]',
                $model->getId(),
                $reportInfo['overall_match_percentage'],
                Turnitin\File\Model::STATUS_COMPLETE
            ),
            Turnitin\File\Model::STATUS_COMPLETE
        );

        $model->setOverallMatchPercentage((float)$reportInfo['overall_match_percentage'])
            ->setInternetMatchPercentage((float)$reportInfo['internet_match_percentage'])
            ->setPublicationMatchPercentage((float)$reportInfo['publication_match_percentage'])
            ->setSubmittedWorksMatchPercentage((float)$reportInfo['submitted_works_match_percentage'])
            ->setStatus(Turnitin\File\Model::STATUS_COMPLETE)
            ->setErrorCode(null)
            ->setErrorMessage(null);

        $this->turnitinMapper->save($model);
    }


    /**
     * Find the current status of the upload - that is, whether turnitin finished
     * processing our upload (whatever that entails). This is a necessary precursor
     * to report generation
     *
     * @param Turnitin\File\Model $model
     * @return array|null
     * @throws BadEndpoint
     * @throws Credentials
     * @throws Turnitin\Exception\Conflict
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function getUploadInfo(Turnitin\File\Model $model): ?array
    {
        /* Find out if this upload finished processing in turnitin town
         */
        $uploadResult = $this->api->retrieve('GET', "/api/v1/submissions/{$model->getTurnitinSubmissionId()}", []);

        if (null !== $uploadResult) {
            /* If the upload was complete, find out about the report itself
             * If it was previously requested, it might be ready now
             * Otherwise, come back later and ask again
             */
            if (self::UPLOAD_COMPLETE === $uploadResult['status']) {
                $this->changeState(
                    $model,
                    sprintf(
                        'Report %s: Turnitin finished processing our upload; setting status [%s]',
                        $model->getId(),
                        Turnitin\File\Model::STATUS_REPORT_PREPARED
                    ),
                    Turnitin\File\Model::STATUS_REPORT_PREPARED
                );

                /* Good to go: can start making the report itself
                 */
                return $uploadResult;
            }

            /* This can occur if your Online Submission html is too short, for example
             * Save the error state
             */
            if (self::UPLOAD_ERROR === $uploadResult['status']) {
                $this->changeToErrorState($model, $uploadResult);
                return null;
            }

            /* When the upload is still PROCESSING, or otherwise being tended to,
             * I guess we just say this
             */
            if (self::UPLOAD_PROCESSING === $uploadResult['status']) {
                $this->logDebug(sprintf(
                    'Report %s: upload status is %s',
                    $model->getId(),
                    $uploadResult['status']
                ));
            }
        }

        /* Default outcome: not ready
         */
        return null;
    }


    /**
     * Decide if the file upload finished; if it's COMPLETE
     * then I guess we're ready to move onto the next step which is to request a report generation
     *
     * @param Turnitin\File\Model $model
     * @param array|null          $existingInfo Existing info as retrieved from
     *                                          the API, or passed from the
     *                                          webhook: null if not known
     * @return array|null
     * @throws BadEndpoint
     * @throws Credentials
     * @throws Turnitin\Exception\Conflict
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function getReportInfo(Turnitin\File\Model $model, ?array $existingInfo): ?array
    {
        // Does the existing report info indicate an error happened?
        // Log that and short-circuit, because attempting to retrieve the
        // submission again will just raise another error.
        if (isset($existingInfo) && self::UPLOAD_ERROR === $existingInfo['status']) {
            $this->changeToErrorState($model, $existingInfo);
            return null;
        }

        try {
            $reportResult = $this->api->retrieve('GET', "/api/v1/submissions/{$model->getTurnitinSubmissionId()}/similarity", []);
        } catch (BadEndpoint $e) {
            /* When the report hasn't been scheduled for generation, this endpoint returns 404
             * imho that really should be InvalidArgument because the endpoint exists, but the
             * remote server hasn't finished populating its contents yet
             */
            $this->changeState(
                $model,
                sprintf(
                    'Report %s: report has not been scheduled for creation yet; doing so now and setting status [%s]',
                    $model->getId(),
                    Turnitin\File\Model::STATUS_REPORT_ANALYSING
                ),
                Turnitin\File\Model::STATUS_REPORT_ANALYSING
            );

            /* Schedule a report for generation (don't know if we need $similarity yet)
             * Also, log the conflict if there is one
             */
            try {
                $similarity = $this->generateReport($model);
            } catch (Turnitin\Exception\Conflict $e) {
                $this->changeToErrorState($model, $existingInfo ?? ['error_code' => 'unknown']);
            }

            return null;
        }

        /* Otherwise, the report was scheduled and something might be found here
         */
        if (null !== $reportResult) {
            /* If the report finished, just return its data
             * for caching locally.
             */
            if (self::REPORT_COMPLETE === $reportResult['status']) {
                return $reportResult;
            }
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
     * Request turnitin to index this file's data, but not generate a report.
     *
     * @param Turnitin\File\Model $model
     * @return array|null
     * @throws BadEndpoint
     * @throws Credentials
     * @throws Turnitin\Exception\Conflict
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function indexHistorical(Turnitin\File\Model $model): ?array
    {
        /* Log the state change
         */
        $this->changeState(
            $model,
            sprintf(
                'Report %s: indexing historical Turnitin submission %s; setting status [%s]',
                $model->getId(),
                $model->getTurnitinSubmissionId(),
                Turnitin\File\Model::STATUS_REPORT_INDEXING
            ),
            Turnitin\File\Model::STATUS_REPORT_INDEXING
        );

        /* No payload required when indexing a document, apparently
         */
        $payload = [];

        $result = $this->api->retrieve('PUT', "/api/v1/submissions/{$model->getTurnitinSubmissionId()}/index", $payload);

        if (null !== $result) {
            if ($result['is_indexed']) {
                $this->changeState(
                    $model,
                    sprintf(
                        'Report %s: Turnitin indexed historical submission id %s; setting status [%s]',
                        $model->getId(),
                        $model->getTurnitinSubmissionId(),
                        Turnitin\File\Model::STATUS_COMPLETE
                    ),
                    Turnitin\File\Model::STATUS_COMPLETE
                );

                return $result;
            }

            /* If you didn't return by now, something went awry
             */
            $this->changeState(
                $model,
                sprintf(
                    'Report %s: Turnitin could not index historical submission id %s; setting status [%s]',
                    $model->getId(),
                    $model->getTurnitinSubmissionId(),
                    Turnitin\File\Model::STATUS_ERROR
                ),
                Turnitin\File\Model::STATUS_ERROR
            );
        }

        return null;
    }


    /**
     * Request turnitin to start manufacturing a Similarity Report for $model
     * This is the last step in this phase, since turnitin normally uses a webhook
     * to tell us when it's done with this manufacturing.
     *
     * @param Turnitin\File\Model $model
     * @return array|null
     * @throws BadEndpoint
     * @throws Credentials
     * @throws Turnitin\Exception\Conflict
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function generateReport(Turnitin\File\Model $model): ?array
    {
        /* Log the state change
         */
        $this->changeState(
            $model,
            sprintf(
                'Report %s: requesting Similarity Report for Turnitin submission %s; setting status [%s]',
                $model->getId(),
                $model->getTurnitinSubmissionId(),
                Turnitin\File\Model::STATUS_REPORT_ANALYSING
            ),
            Turnitin\File\Model::STATUS_REPORT_ANALYSING
        );

        /* These use the default search repositories for now
         * TODO We can refine these search repos later
         */
        $payload = [
            'indexing_settings' => [
                'add_to_index' => true
            ],
            'generation_settings' => [
                'search_repositories' => [
                    'INTERNET',
                    'SUBMITTED_WORK',
                    'PUBLICATION',
                    'CROSSREF',
                    'CROSSREF_POSTED_CONTENT'
                ],
                'auto_exclude_self_matching_scope' => 'ALL',
            ],
            'view_settings' => [
                'exclude_bibliography' => true,
                'exclude_citations' => true,
            ],
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
     * Log to Logger if one was provided (used in cli tools)
     * Change the state at the same time
     *
     * @param Turnitin\File\Model $model
     * @param string         $message
     * @param string         $status
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function changeState(Turnitin\File\Model $model, string $message, string $status): void
    {
        $this->logDebug($message);

        /* Clear error messages from previously-successful stages
         * because they are no longer relevant; this allows us to
         * distinguish status states being set in a normal processing
         * or whether something died -whilst- in that state, giving
         * support a diagnosis and resolution option
         */
        $model->setErrorCode(null)
            ->setErrorMessage(null)
            ->setStatus($status);

        $this->turnitinMapper->save($model);
    }


    /**
     * Change the state of this report to an error state.
     *
     * @param Turnitin\File\Model $model
     * @param array               $uploadResult
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function changeToErrorState(Turnitin\File\Model $model, array $uploadResult): void
    {
        $this->logWarning(
            sprintf(
                'Report %s: upload contains error code %s',
                $model->getId(),
                $uploadResult['error_code']
            )
        );

        $model->setErrorCode($uploadResult['error_code'])
              ->setStatus(Turnitin\File\Model::STATUS_ERROR);

        $this->turnitinMapper->save($model);
    }
}
