<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Cloud;



use Schoolbox\MobileApp\RegistryMapper;

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
        $this->services[] = $service;
        return $this;
    }


    /**
     * @param int   $userId
     * @param array $data
     */
    public function push($userId, $data)
    {

    }

}
