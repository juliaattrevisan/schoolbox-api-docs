<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Base\ShadowTable;
use Schoolbox\External\Connector\TassApi\Base\Endpoint as TassApiEndpoint;
use Schoolbox\External\Connector\Compass\Base\Endpoint as CompassEndpoint;
use Schoolbox\GenericCollection;
use Schoolbox\RoleMapper;
use SensitiveParameter;

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
    public const COMPASS = 'compass';


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
        #[SensitiveParameter] string $key,
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
                $composites = (new TassApiEndpoint\Calendar\Factory(
                    $this->logger,
                    $this->perform,
                    $this->host,
                    $this->key,
                    \Schoolbox\External\Profile\Student\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Profile\Staff\Mapper::getInstance()->disableCache(),
                    \Schoolbox\CampusMapper::getInstance()->disableCache(),
                    \Schoolbox\Calendar\EventTypeMapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Roletype\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Campus\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\User\Mapper::getInstance()->disableCache(),
                    RoleMapper::getInstance()->disableCache()
                ))->produce();
                break;

            case self::COMPASS:
                $composites = (new CompassEndpoint\Calendar\Factory(
                    $this->logger,
                    $this->perform,
                    $this->host,
                    $this->key,
                    \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\User\Mapper::getInstance()->disableCache()
                ))->produce();
                break;

            default:
                // Use manual csv imports
                return null;
        }

        // exit early if composite doesn't exist
        // we probably shouldn't continue if the sis calendar import failed
        return $composites
            ?->prepend(
                new ShadowTable\Calendar\Enable(
                    $this->logger,
                    $this->perform,
                    \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Roletype\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Campus\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\User\Mapper::getInstance()->disableCache(),
                )
            )
            ->add(
                new ShadowTable\Calendar\Disable(
                    $this->logger,
                    $this->perform,
                    \Schoolbox\External\Event\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Roletype\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\Campus\Mapper::getInstance()->disableCache(),
                    \Schoolbox\External\Event\User\Mapper::getInstance()->disableCache(),
                )
            );
    }
}
