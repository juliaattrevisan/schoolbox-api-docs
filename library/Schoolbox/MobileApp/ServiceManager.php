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
use Psr\Log\LoggerInterface;
use RangeException;
use Schoolbox\Collection;

/**
 * Class ServiceManager
 * @package Schoolbox\MobileApp
 */
class ServiceManager implements IServiceManager
{

    /** @var RegistryMapper */
    protected $registryMapper;

    /** @var LoggerInterface */
    protected $log;


    /**
     * @param RegistryMapper     $registryMapper
     * @param LoggerInterface    $log
     */
    public function __construct(LoggerInterface $log, RegistryMapper $registryMapper)
    {
        $this->registryMapper = $registryMapper;
        $this->log = $log;
    }


    /**
     * @param int   $userId
     * @param array $data
     */
    public function push($userId, $data)
    {
        $registrations = $this->registryMapper->findByColumn('user_id', $userId);

        if (count($registrations) === 0) {
            $this->log->notice("Received push for user $userId, but they have no devices registered");
            return;
        }

        // TODO retrieve the list of unread messages for this user - until SA-359 is complete, this is not possible...
        $unreadCount = 1;

        foreach ($this->findRegistrationsByUser($userId) as $appId => $appRegistrations) {
            // TODO verify that this doesn't repeat work in CLI context
            $service = ServiceMapper::getInstance()->findById($appId);
            $tokens  = $appRegistrations->getColumnValues('token');

            // ensure the log is set
            $service->setLog($this->log);

            try {
                $this->pushToService($service, $tokens, $data, $unreadCount);

            } catch (InvalidArgumentException $e) {
                // service not available
                $this->log->notice("Failed push to {$service->getProviderName()} provider '{$service->getAppIdentifier()}': {$e->getMessage()}", [ $e ]);
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
        $appId    = $service->getAppIdentifier();

        try {
            $this->log->info(sprintf('Sending data to %d device%s on "%s" (%s)', count($tokens), count($tokens) === 1 ? '' : 's', $appId, $provider));
            $service->send($tokens, $data, $unreadCount);

        } catch (OutOfBoundsException $e) {
            // bad API key, which will require a daemon restart, so remove this service from use
            $this->log->warning("Service '{$service->getProviderName()}' unusable: {$e->getMessage()}", [ $e ]);

        } catch (RangeException $e) {
            // TODO implement backoff-and-retry.
            // TODO $e->getCode() will contain a Retry-After TTL (in seconds), or 0 if exponential backoff is to be implemented
            $this->log->info($e->getMessage(), [ $e ]);
        }
    }


    /**
     * @param int $userId
     * @return Collection|Collection[]|Registry[][]
     */
    private function findRegistrationsByUser($userId)
    {
        $registrations = RegistryMapper::getInstance()->findByColumn('user_id', 6);

        return $registrations->groupByCallback(function (Registry $registration) {
            return $registration->getService()->getAppIdentifier();
        });
    }

}
