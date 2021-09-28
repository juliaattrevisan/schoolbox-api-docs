<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin;

use Alaress\Web\Controller\Base;

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
    private const HEADER_EVENT_TYPE = 'X-Turnitin-EventType';


    /**
     * The webhook header value for turnitin when it finishes an upload
     * https://developers.turnitin.com/docs/tca#event-types
     */
    private const SUBMISSION_COMPLETE = 'SUBMISSION_COMPLETE';


    /**
     * The webhook header value for turnitin when it finishes a report
     * https://developers.turnitin.com/docs/tca#event-types
     */
    private const SIMILARITY_COMPLETE = 'SIMILARITY_COMPLETE';


    /**
     * The webhook header value for turnitin when it finishes saving
     * changes to one of its reports
     * https://developers.turnitin.com/docs/tca#event-types
     */
    private const SIMILARITY_UPDATED = 'SIMILARITY_UPDATED';


    /**
     * @var Mapper
     */
    private Mapper $turnitinMapper;


    /**
     * Controller constructor.
     */
    public function __construct(Mapper $turnitinMapper)
    {
        $this->turnitinMapper = $turnitinMapper;
    }


    /**
     * This method fires when Turnitin finishes processing our upload
     *
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function postUploadComplete(): void
    {
        /* Only accept the SUBMISSION_COMPLETE header
         */
        if (self::SUBMISSION_COMPLETE !== $this->getRequest()->headers->get(self::HEADER_EVENT_TYPE)) {
            throw new \DomainException(sprintf(
                'Turnitin accessed %s webhook with non-matching webhook header: %s = %s',
                'postUploadComplete',
                self::HEADER_EVENT_TYPE,
                $this->getRequest()->headers->get(self::HEADER_EVENT_TYPE)
            ));
        }

        $api = $this->produce();

        print_r('Webhook: upload completed');
    }


    /**
     * This method fires when Turnitin finishes processing our report
     *
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function postReportComplete(): void
    {
        /* Only accept the SIMILARITY_COMPLETE header
         */
        if (self::SIMILARITY_COMPLETE !== $this->getRequest()->headers->get(self::HEADER_EVENT_TYPE)) {
            throw new \DomainException(sprintf(
                'Turnitin accessed %s webhook with non-matching webhook header: %s = %s',
                'postReportComplete',
                self::HEADER_EVENT_TYPE,
                $this->getRequest()->headers->get(self::HEADER_EVENT_TYPE)
            ));
        }

        $api = $this->produce();

        print_r('Webhook: report completed');
    }


    /**
     * This method fires when Turnitin finishes processing user-made changes to a report
     *
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function postReportUpdated(): void
    {
        /* Only accept the SIMILARITY_COMPLETE header
         */
        if (self::SIMILARITY_UPDATED !== $this->getRequest()->headers->get(self::HEADER_EVENT_TYPE)) {
            throw new \DomainException(sprintf(
                'Turnitin accessed %s webhook with non-matching webhook header: %s = %s',
                'postReportUpdated',
                self::HEADER_EVENT_TYPE,
                $this->getRequest()->headers->get(self::HEADER_EVENT_TYPE)
            ));
        }

        $api = $this->produce();

        print_r('Webhook: report updated');
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
    private function produce(): Api
    {
        /* This will continually throw exceptions to sentry if someone turns off
         * turnitin, but turnitin still fires its webhooks
         *
         * This could get noisy
         *
         * Alternatively, just return null and check for Api instances being
         * produced in the webhook endpoints above
         */
        if (!$this->turnitinMapper->isConfigured()) {
            throw new \DomainException('Turnitin configuration invalid.');
        }

        return new Api(
            $this->getConfigLoader()->get('site_version'),
            $this->getConfigLoader()->get('turnitin_api_host'),
            $this->getConfigLoader()->get('turnitin_api_key')
        );
    }
}
