<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin;

use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Learning\Turnitin;

/**
 * Turnitin Webhooks route through this controller
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'turnitin';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Anyone can post to the endpoint, for now
         *
         * ...but we should really be validating the Turnitin payloads before
         * blindly accepting them
         */
        $postPerms = null;

        $this->register('POST', '/webhook', 'postWebhook', $postPerms);

        /* These are the individual controller methods that you can test in isolation
         */
//        $this->register('POST', '/upload-complete', 'postUploadComplete', $postPerms);
//        $this->register('POST', '/report-complete', 'postReportComplete', $postPerms);
//        $this->register('POST', '/report-updated', 'postReportUpdated', $postPerms);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller($this->getMapperFactory()->get(Turnitin\File\Mapper::class));
    }
}
