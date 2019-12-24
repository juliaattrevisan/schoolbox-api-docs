<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable;

use Schoolbox\External\Connector\Kamar\Base\Endpoint\Specification as BaseSpecification;

/**
 * Class Specification
 *
 * This explores an xml output to check whether certain Timetable-related nodes exist
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable
 */
class Specification extends BaseSpecification
{

    /**
     * Timetable nodes
     *
     * @var array
     */
    protected $paths = [
    ];
}
