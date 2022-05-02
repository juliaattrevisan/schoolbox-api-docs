<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\TassApi\Base\Endpoint as TassApiEndpoint;
use Schoolbox\GenericCollection;
use Schoolbox\RoleMapper;

/**
 * Class CalendarFactory
 *
 * Create a set of Composites which import Event data
 * from third-party endpoints into schoolbox
 *
 * @package Schoolbox\External\Importer\Endpoint
 */
class CalendarFactory implements IFactory
{

    /**
     * The family of importers that we can spawn
     */
    public const TASS_API = 'tassapi';


    /**
     * API Endpoint url
     *
     * @var string
     */
    private string $host;


    /**
     * API Endpoint access token
     *
     * @var string
     */
    private string $key;


    /**
     * @var bool
     */
    private bool $perform;


    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;


    /**
     * CalendarFactory constructor.
     *
     * @param string          $host
     * @param string          $key
     * @param bool            $perform
     * @param LoggerInterface $logger
     */
    public function __construct(
        string $host,
        string $key,
        bool $perform,
        LoggerInterface $logger
    ) {
        $this->host = $host;
        $this->key = $key;
        $this->perform = $perform;
        $this->logger = $logger;
    }


    /**
     * @param string|null $file
     * @param string      $type
     *
     * @return null|GenericCollection
     * @throws \DomainException
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function produce(?string $file, string $type): ?GenericCollection
    {
        switch ($type) {
            case self::TASS_API:
                return (new TassApiEndpoint\Calendar\Factory(
                    $this->logger,
                    $this->perform,
                    $this->host,
                    $this->key,
                    \Schoolbox\CampusMapper::getInstance(),
                    \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                    \Schoolbox\External\Event\Mapper::getInstance(),
                    \Schoolbox\External\Event\Roletype\Mapper::getInstance(),
                    \Schoolbox\External\Event\Campus\Mapper::getInstance(),
                    RoleMapper::getInstance()
                ))->produce();

            default:
                // Use manual csv imports
                return null;
        }
    }
}
