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
use OutOfBoundsException;

class ServiceManager
{
    /** @var ServiceInterface */
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
        // index by provider name
        $this->services[$service->getProviderName()] = $service;
        return $this;
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
                $this->getService($provider)->send($registries->getColumnValues('token'), $data);

            } catch (OutOfBoundsException $e) {
                echo $e; // TODO use logger
            }
        }
    }


    /**
     * @param string $providerName
     * @throws OutOfBoundsException
     * @return ServiceInterface
     */
    protected function getService($providerName)
    {
        if (isset($this->services[$providerName])) {
            return $this->services[$providerName];
        }

        throw new OutOfBoundsException("Provider '$providerName' not set.");
    }

}
