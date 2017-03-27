<?php
/**
 * Schoolpro implementation of external events
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Schoolpro\Base;

use Schoolbox\External\Connector\IExternalEvents;
use Schoolbox\External\Connector\Base;

class ExternalEvents extends Base implements IExternalEvents
{
    /**
     * @inheritdoc
     */
    public function getExternalEvents($user, $startDate, $endDate)
    {
        return false;
    }
}
