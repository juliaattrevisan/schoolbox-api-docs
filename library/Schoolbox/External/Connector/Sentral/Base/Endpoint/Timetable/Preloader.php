<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Api;
use Schoolbox\External\Connector\Sentral\Base\Endpoint\Registry;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class Preloader
 *
 * When we imported Students we remembered their ids so we can fetch their
 * individual timetables here
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Timetable
 */
class Preloader implements IStrategy
{
    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * @var Api
     */
    private $api;


    /**
     * @var Registry
     */
    private $registry;


    /**
     * Preloader constructor.
     *
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param Api             $api
     * @param Registry        $registry
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Api $api,
        Registry $registry
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->registry = $registry;
    }


    /**
     * For every student we encountered, get their timetable
     * for the current week, Mon-Fri inclusive
     */
    public function import(): void
    {

        /* Iterate through all the students we saw
         */
        $ids = $this->registry->keys();

        /* Use timetable-lessons endpoint with appropriate dates for the Monday of this week
         * to the Friday of this week inclusive
         */
        

        /* Some endpoint accesses result in an endpoint saying you didn't provide a thing
         * called a "timeview", i.e. "Missing timeview student information for the given student"
         *
         * This means they don't have any timetable data, but it results in a endpoint error
         * from sentral and an exception being thrown by us. There's no api docs about it,
         * I had to figure it out the hard way
         *
         * We can't process any timetables in those cases, so log it and continue
         */

        /* Otherwise, remember it in the Registry instance because we left ourselves
         * a nice spot for it to fit
         */

    }
}
