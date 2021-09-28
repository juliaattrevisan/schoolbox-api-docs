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
     * Controller constructor.
     */
    public function __construct()
    {
    }


    /**
     * This method fires when Turnitin finishes processing our upload
     */
    public function getUploadComplete(): void
    {
        new \Alaress_Probe('Webhook: submission completed');
    }


    /**
     * This method fires when Turnitin finishes processing our report
     */
    public function getReportComplete(): void
    {
        new \Alaress_Probe('Webhook: report completed');
    }
}
