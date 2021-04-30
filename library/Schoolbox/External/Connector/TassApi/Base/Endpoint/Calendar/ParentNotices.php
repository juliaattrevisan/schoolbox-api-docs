<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar;

use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\Role;


/**
 * Class ParentNotices
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar
 */
class ParentNotices extends Base implements IStrategy
{

    /**
     * Endpoint specific to this event import
     *
     * @var string
     */
    protected string $endpoint = 'getParentNotices';


    /**
     * The RoleType to assign to each event in this import
     *
     * @var string
     */
    protected string $roleType = Role::PARENT;
}