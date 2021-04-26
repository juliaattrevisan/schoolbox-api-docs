<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\GenericCollection;


/**
 * Class Factory
 *
 * The overall import recipe. A composite of multiple classes each of which has its own
 * particular responsibility. The composite execute each of its pieces in sequence.
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar
 */
class Factory
{

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * @var string
     */
    private string $host;


    /**
     * @var string
     */
    private string $key;


    /**
     * Factory constructor.
     *
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param string                          $host
     * @param string                          $key
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        string $host,
        string $key
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->host = $host;
        $this->key = $key;
    }


    /**
     * @return GenericCollection
     */
    public function produce(): GenericCollection
    {
        $this->log->info('Accessing api endpoint');

        $modules = [
            Codec\DSN::MODULE_LMS_INTEGRATION,
            Codec\DSN::MODULE_STUDENT_DETAILS,
            Codec\DSN::MODULE_IDENTITY_MANAGEMENT
        ];

        $api = [];

        foreach ($modules as $module) {
            $api[$module] = new Api($this->host, $this->key, $module);
        }

        /* Purge previously-imported events and event relationships
         */
        return (new GenericCollection(IStrategy::class))
            ->add(new Prepare($this->log, $this->perform))
            ->add(new TeacherNotices($this->log, $this->perform, $api))
            ->add(new TeacherCalendar($this->log, $this->perform, $api))
            ->add(new StudentNotices($this->log, $this->perform, $api))
            ->add(new StudentCalendar($this->log, $this->perform, $api))
            ->add(new ParentNotices($this->log, $this->perform, $api))
            ->add(new ParentCalendar($this->log, $this->perform, $api))
            ->add(new Cleanup($this->log, $this->perform))
            ;
    }
}
