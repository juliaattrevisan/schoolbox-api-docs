<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\MobileApp;

use Alaress\Async\Notification\Cloud\ServiceInterface;
use InvalidArgumentException;
use OutOfBoundsException;
use OverflowException;
use Psr\Log\LoggerInterface;
use RangeException;

class ServiceManager
{

    /** @var ServiceInterface[] */
    protected $services = array();

    /** @var RegistryMapper */
    protected $registryMapper;

    /** @var LoggerInterface */
    protected $log;


    /**
     * @param ServiceInterface[] $services
     * @param RegistryMapper     $registryMapper
     * @param LoggerInterface    $log
     */
    public function __construct(array $services, LoggerInterface $log, RegistryMapper $registryMapper = null)
    {
        $this->registryMapper = $registryMapper ?: RegistryMapper::getInstance();
        $this->log = $log;

        foreach ($services as $service) {
            // ensure typesafety
            $this->addService($service);
        }
    }


    /**
     * @param ServiceInterface $service
     * @return self
     */
    public function addService(ServiceInterface $service)
    {
        $provider = $service->getProviderName();

        // can't set it more than once
        if (array_key_exists($provider, $this->services)) {
            throw new OverflowException("Cannot redeclare service '$provider'.");
        }

        // apply our logger to this server
        $service->setLog($this->log);

        // index by provider name
        $this->services[$provider] = $service;
        $this->log->debug("Added cloud notification provider '$provider'");
        return $this;
    }


    /**
     * You should only do this if a service cannot be used, eg. the API key in config is invalid.
     *
     * @param string $provider
     */
    protected function dropService($provider) {
        if (array_key_exists($provider, $this->services)) {
            $this->services[$provider] = null;
            $this->log->debug("Dropped cloud notification provider '$provider'");
        }
    }


    /**
     * @param int   $userId
     * @param array $data
     */
    public function push($userId, $data)
    {
        $registered = $this->registryMapper->findByColumn('user_id', $userId)->groupBy('provider');

        if (count($registered) == 0) {
            $this->log->notice("Received push for user $userId, but they have no devices registered");
            return;
        }

        // TODO retrieve the list of unread messages for this user - until SA-359 is complete, this is not possible...
        $unreadCount = 1;

        foreach ($registered as $provider => $registries) {
            try {
                $service = $this->getService($provider);
                $tokens  = $registries->getColumnValues('token');
                $this->pushToService($service, $tokens, $data, $unreadCount);

            } catch (InvalidArgumentException $e) {
                // service not available
                $this->log->notice("Failed push to '$provider': {$e->getMessage()}", [ $e ]);
            }
        }
    }


    /**
     * @param ServiceInterface $service
     * @param array            $tokens
     * @param array            $data
     * @param int              $unreadCount
     */
    protected function pushToService(ServiceInterface $service, $tokens, $data, $unreadCount = 0)
    {
        $provider = $service->getProviderName();

        try {
            $this->log->info("Sending data to " . count($tokens) . " $provider devices");
            $service->send($tokens, $data, $unreadCount);

        } catch (OutOfBoundsException $e) {
            // bad API key, which will require a daemon restart, so remove this service from use
            $this->dropService($provider);
            $this->log->warning("Dropping unusable service '$provider': {$e->getMessage()}", [ $e ]);

        } catch (RangeException $e) {
            // TODO implement backoff-and-retry.
            // TODO $e->getCode() will contain a Retry-After TTL (in seconds), or 0 if exponential backoff is to be implemented
            $this->log->info($e->getMessage(), [ $e ]);
        }
    }


    /**
     * @param string $providerName
     * @throws InvalidArgumentException
     * @return ServiceInterface
     */
    protected function getService($providerName)
    {
        if (isset($this->services[$providerName])) {
            return $this->services[$providerName];
        }

        throw new InvalidArgumentException("Provider '$providerName' not available.");
    }

}
