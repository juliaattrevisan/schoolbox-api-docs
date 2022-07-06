<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin;

use Alaress\Web\Controller\Base;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Learning\Turnitin\Exception\BadEndpoint;
use Schoolbox\Learning\Turnitin\Exception\Credentials;

/**
 * A set of webhooks that Turnitin access when it finishes certain steps
 *
 * When turnitin finishes a step in its processing, it POSTs something
 * to the corresponding webhook endpoint that we've set up here
 *
 * Each of the webhook functions uses the same logic found in File and Report
 * processing that we periodically execute as a backup maneuver
 */
class Controller extends Base
{
    /**
     * The header which turnitin uses to tell us which step it just did
     */
    public const HEADER_EVENT_TYPE = 'X-Turnitin-EventType';


    /**
     * The webhook header value for turnitin when it finishes an upload
     * https://developers.turnitin.com/docs/tca#event-types
     */
    public const SUBMISSION_COMPLETE = 'SUBMISSION_COMPLETE';


    /**
     * The webhook header value for turnitin when it finishes a report
     * https://developers.turnitin.com/docs/tca#event-types
     */
    public const SIMILARITY_COMPLETE = 'SIMILARITY_COMPLETE';


    /**
     * The webhook header value for turnitin when it finishes saving
     * changes to one of its reports
     * https://developers.turnitin.com/docs/tca#event-types
     */
    public const SIMILARITY_UPDATED = 'SIMILARITY_UPDATED';


    /**
     * @var Turnitin\File\Mapper
     */
    private Turnitin\File\Mapper $turnitinFileMapper;


    /**
     * Controller constructor.
     */
    public function __construct(Turnitin\File\Mapper $turnitinFileMapper)
    {
        $this->turnitinFileMapper = $turnitinFileMapper;
    }


    /**
     * Based on the header, decide how to process it
     *
     * @throws BadEndpoint
     * @throws Credentials
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postWebhook(): void
    {
        /* Pick a destination based on the X-Turnitin headers in the payload, but
         * you'd probably want to do some further validation here before you continue
         */
        switch ($this->getRequest()->headers->get(self::HEADER_EVENT_TYPE)) {
            /* Turnitin finished processing our upload
             */
            case self::SUBMISSION_COMPLETE:
                $this->postUploadComplete();
                break;

            /* Turnitin finished generating a report
             */
            case self::SIMILARITY_COMPLETE:
                $this->postReportComplete();
                break;

            /* User updated a report through the turnitin interface
             */
            case self::SIMILARITY_UPDATED:
                $this->postReportUpdated();
                break;

            /* Unknown header; do nothing
             */
            default:
                break;
        }
    }


    /**
     * This method fires when Turnitin finishes processing our upload
     *
     * @throws BadEndpoint
     * @throws Credentials
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postUploadComplete(): void
    {
        $currentFunction = __FUNCTION__;

        /* Only accept the SUBMISSION_COMPLETE header
         */
        $data = $this->generateModelAndPayload(self::SUBMISSION_COMPLETE, $currentFunction, 'id');

        /* If we found it, tell turnitin to make a report for it
         * Everything after here is kind of a rip from the cli tools
         */
        (new Turnitin\File\Process\Report($this->produceApi(), $this->turnitinFileMapper))->getReportInfo($data['model'], $data['payload']);
    }


    /**
     * This method fires when Turnitin finishes processing our report
     *
     * @throws Exception\Exception
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postReportComplete(): void
    {
        $currentFunction = __FUNCTION__;

        /* Only accept the SIMILARITY_COMPLETE header
         */
        $data = $this->generateModelAndPayload(self::SIMILARITY_COMPLETE, $currentFunction, 'submission_id');

        /* If we found it, tell turnitin to save some data locally
         * Everything after here is kind of a rip from the cli tools
         */
        (new Turnitin\File\Process\Report($this->produceApi(), $this->turnitinFileMapper))->cacheLocally($data['model'], $data['payload']);
    }


    /**
     * This method fires when Turnitin finishes processing user-made changes to a report
     *
     * @throws Exception\Exception
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postReportUpdated(): void
    {
        $currentFunction = __FUNCTION__;

        /* Only accept the SIMILARITY_UPDATED header
         */
        $data = $this->generateModelAndPayload(self::SIMILARITY_UPDATED, $currentFunction, 'submission_id');

        /* If we found it, tell turnitin to save some data locally
         * Everything after here is kind of a rip from the cli tools
         */
        (new Turnitin\File\Process\Report($this->produceApi(), $this->turnitinFileMapper))->cacheLocally($data['model'], $data['payload']);
    }


    /**
     * Generate the local $model instance and the turnitin payload for usage
     * This turned out to be common across all the webhooks, so condense it
     * all into a SPOC and use that instead
     *
     * $currentFunction is used for error reporting into sentry so you can tell
     * which webhook went awry
     *
     * @param string $headerKey
     * @param string $currentFunction
     * @param string $submissionIdentifierKey
     * @return array
     * @throws Exception\Exception
     */
    private function generateModelAndPayload(string $headerKey, string $currentFunction, string $submissionIdentifierKey): array
    {
        /* Only accept a specific header
         */
        if ($headerKey !== $this->getRequest()->headers->get(self::HEADER_EVENT_TYPE)) {
            throw new Turnitin\Exception\Exception(sprintf(
                'Turnitin accessed %s webhook with non-matching webhook header: %s = %s',
                $currentFunction,
                self::HEADER_EVENT_TYPE,
                $this->getRequest()->headers->get(self::HEADER_EVENT_TYPE)
            ));
        }

        /* Receive payloads and start digging through its guts
         */
        $rawPayload = file_get_contents('php://input');

        try {
            $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new Turnitin\Exception\Exception(sprintf(
                'Turnitin sent %s json payload but we cannot decode it: %s',
                'postUploadComplete',
                $rawPayload
            ));
        }

        /* If you got this far you should have a payload that you can use
         * Dig out the submission identifier
         *
         * Note that the submission identifier is different here than in
         * postUploadComplete! All you copypasta nerds (including me) be
         * careful pls, don't mass pasta without checking the spec first
         */
        if (!isset($payload[$submissionIdentifierKey])) {
            throw new Turnitin\Exception\Exception(sprintf(
                'Turnitin submission identifier key %s does not exist in the payload; cannot find turnitin model.',
                $submissionIdentifierKey
            ));
        }

        $turnitinSubmissionId = $payload[$submissionIdentifierKey];

        /* Find the corresponding report, if it exists
         */
        try {
            $model = $this->turnitinFileMapper->findOneByColumn('turnitin_submission_id', $turnitinSubmissionId);
        } catch (\Schoolbox\Mapper\Exception\Deleted $deleted) {
            $model = $deleted->getDeletedObject();
        } catch (\Schoolbox\Mapper\Exception\NotFound $e) {
            throw $e;
        }

        return [
            'model' => $model,
            'payload' => $payload
        ];
    }


    /**
     * Check whether turnitin configuration was provided. If it wasn't provided
     * then we can't/shouldn't process anything
     *
     * You don't have access to getConfigLoader() in the constructor so you can't
     * exit any earlier than this, afaict
     *
     * @return Api
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    private function produceApi(): Api
    {
        /* This will continually throw exceptions to sentry if someone turns off
         * turnitin, but turnitin still fires its webhooks
         *
         * This could get noisy
         *
         * Alternatively, just return null and check for Api instances being
         * produced in the webhook endpoints above
         */
        if (!(new Helper($this->getConfigLoader()))->isConfigured()) {
            throw new Turnitin\Exception\Exception('Turnitin configuration invalid.');
        }

        return new Api(
            $this->getConfigLoader()->get('site_version'),
            $this->getConfigLoader()->get('turnitin_api_host'),
            $this->getConfigLoader()->get('turnitin_api_key')
        );
    }
}
