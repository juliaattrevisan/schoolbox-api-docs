<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing;

use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;

class DispatchStrategy extends DefaultDispatchStrategy
{

    const TASK_ROUTE = 'route';

    /**
     * Get the name of the strategy currently being used
     *
     * @return string
     */
    public function getName()
    {
        return 'Route';
    }


    /**
     * @return string
     */
    public function getCommand()
    {
        return SITE_root . "/tools/notif/route/worker.php";
    }


    /**
     * @return array
     */
    public function getSupportedActions()
    {
        return [ self::TASK_ROUTE ];
    }

}