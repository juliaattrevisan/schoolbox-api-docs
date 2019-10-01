<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Event;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * @var IConnector
     */
    private $databaseInternal;

    public function __construct(IConnector $databaseInternal)
    {
        $this->databaseInternal           = $databaseInternal;
    }

    public function getEvent()
    {
        echo 'test';
        die();
    }
}
