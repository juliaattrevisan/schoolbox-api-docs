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
 * Class StudentNotices
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar
 */
class StudentNotices extends Base implements IStrategy
{

    /**
     * Endpoint specific to this event import
     *
     * @var string
     */
    protected string $endpoint = 'getStudentNotices';


    /**
     * The RoleType to assign to each event in this import
     *
     * @var string
     */
    protected string $roleType = Role::STUDENT;
}