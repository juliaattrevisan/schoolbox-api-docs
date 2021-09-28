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
     */
    public function getUploadComplete(): void
    {
        $api = $this->produce();

        new \Alaress_Probe('Webhook: submission completed');
    }


    /**
     * This method fires when Turnitin finishes processing our report
     */
    public function getReportComplete(): void
    {
        $api = $this->produce();

        new \Alaress_Probe('Webhook: report completed');
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
