<?php
/**
 * Tass implementation of external events
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Tass\Base;

use Schoolbox\External\Connector\ExternalEventsInterface;
use Schoolbox\External\Connector\Base;

class ExternalEvents extends Base implements ExternalEventsInterface
{
    /**
     * @inheritdoc
     */
    public function getExternalEvents($user, $startDate, $endDate)
    {
        return false;
    }
}
