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
use RangeException;

class ServiceManager
{

    /** @var ServiceInterface[] */
    protected $services = array();

    /** @var RegistryMapper */
    protected $registryMapper;


    /**
     * @param ServiceInterface[] $services
     * @param RegistryMapper     $registryMapper
     */
    public function __construct(array $services = array(), RegistryMapper $registryMapper = null)
    {
        $this->registryMapper = $registryMapper ?: RegistryMapper::getInstance();

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

        // index by provider name
        $this->services[$provider] = $service;
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
        }
    }


    /**
     * @param int   $userId
     * @param array $data
     */
    public function push($userId, $data)
    {
        $registered = $this->registryMapper->findByColumn('user_id', $userId)->groupBy('provider');

        foreach ($registered as $provider => $registries) {
            try {
                $service = $this->getService($provider);
                $tokens  = $registries->getColumnValues('token');
                $this->pushToService($service, $tokens, $data);

            } catch (InvalidArgumentException $e) {
                // service not available
                echo $e; // TODO use logger
            }
        }
    }


    /**
     * @param ServiceInterface $service
     * @param array            $tokens
     * @param array            $data
     */
    protected function pushToService(ServiceInterface $service, $tokens, $data)
    {
        $provider = $service->getProviderName();

        try {
            $service->send($tokens, $data);

        } catch (OutOfBoundsException $e) {
            // bad API key, which will require a daemon restart, so remove this service from use
            $this->dropService($provider);
            echo $e; // TODO use logger

        } catch (RangeException $e) {
            // TODO implement backoff-and-retry.
            // TODO $e->getCode() will contain a Retry-After TTL (in seconds), or 0 if exponential backoff is to be implemented
            echo $e;
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
