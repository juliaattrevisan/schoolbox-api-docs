<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\MobileApp;

use Alaress\Async\Notification\Cloud;
use Alaress\Async\Notification\Cloud\ServiceInterface;
use Alaress\Async\Notification\Instance\Instance;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;
use RangeException;
use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Subscription\Redis\UnreadRetriever;
use Schoolbox\Resource\Storage;

/**
 * Class ServiceManager
 * @package Schoolbox\MobileApp
 */
class ServiceManager implements IServiceManager
{
    /**
     * Never use the test service: always use a production service.
     */
    public const SERVICE_TYPE_TEST = 1;

    /**
     * Use the test service based on the instance
     * * developer instances always use the test service
     * * staging instances use the test service unless they are sandboxes
     * * production instances always use the production service
     */
    public const SERVICE_TYPE_BASED_ON_INSTANCE_TYPE = 2;

    /**
     * Always use a production service: never use the test service.
     */
    public const SERVICE_TYPE_PRODUCTION = 3;

    /**
     * Constructor
     *
     * @param LoggerInterface         $log             Logger.
     * @param RegistryMapper          $registryMapper  Mapper for fetching registrations.
     * @param Schoolbox\Instance      $serverInstance  Struct within which instance data is stored.
     * @param string                  $appTitle        The app's title: matches `config.title`.
     *                                                 May be used as the title of notifications.
     * @param Storage\FilesystemProxy $filesystemProxy The file system on which the APNS config is stored.
     * @param self::SERVICE_TYPE_*    $serviceType     Service type to use: test, production or based on instance type.
     * @param UnreadRetriever         $unreadRetriever Retriever to get the number of unread messages in queue.
     */
    public function __construct(
        private LoggerInterface $log,
        private RegistryMapper $registryMapper,
        private Schoolbox\Instance $serverInstance,
        private string $appTitle,
        private Storage\FilesystemProxy $filesystemProxy,
        private UnreadRetriever $unreadRetriever,
        private int $serviceType = self::SERVICE_TYPE_BASED_ON_INSTANCE_TYPE,
        private ?Schoolbox\IConfigLoader $configLoader = null,
    ) {
    }


    /**
     * @param int      $userId
     * @param Instance $instance
     */
    public function push($userId, Instance $instance)
    {
        $registrations = $this->registryMapper->countByColumn('user_id', $userId);

        $recipient = (new Schoolbox\UserMapper())->findById($userId);

        $isRecipientParent = $recipient->getRole()->isParent();
        //Check to see if TASS Orbit is set up as a delivery provider
        //Only parents should receive the notifications through TASS Orbit
        $tassProvider = $isRecipientParent ?
            (ServiceMapper::getInstance())->countByColumn('provider', Registry::PROVIDER_TASS_ORBIT) :
            0;

        //User didn't registered anything and TASS is not set up
        if ($registrations === 0 && $tassProvider === 0) {
            $this->log->notice("Received push for user $userId, but they have no devices registered");
            return;
        }

        $unread = $this->unreadRetriever->getUnread(INSTANCE_ID, $userId);
        $unreadCount = count($unread);

        foreach ($this->findRegistrationsByUser($recipient) as $provider => $appRegistrations) {
            foreach ($appRegistrations as $appIdentifier => $reg) {
                $service = $this->getService($reg['service']);

                /** @var Collection $apps */
                $apps = $reg['registrations'];

                $tokens = $apps->getPropertyValues('token');

                // ensure the log is set
                $service->setLog($this->log);

                try {
                    $this->pushToService($service, $tokens, $instance, $unreadCount, $recipient);
                } catch (InvalidArgumentException $e) {
                    // service not available
                    $this->log->notice("Failed push to {$service->getProviderName()} provider '{$service->getAppIdentifier()}': {$e->getMessage()}", [ 'exception' => $e ]);
                }
            }
        }
    }


    /**
     * @param ServiceInterface $service
     * @param array            $tokens
     * @param Instance         $instance
     * @param int              $unreadCount
     */
    protected function pushToService(ServiceInterface $service, $tokens, Instance $instance, $unreadCount = 0, ?Schoolbox\User $recipient = null)
    {
        $provider = $service->getProviderName();
        $appId    = $service->getAppIdentifier();

        try {
            $this->log->info(sprintf('Sending data to %d device%s on "%s" (%s)', count($tokens), count($tokens) === 1 ? '' : 's', $appId, $provider));
            $service->send($tokens, $instance, $unreadCount, $recipient);
        } catch (OutOfBoundsException $e) {
            // bad API key, which will require a daemon restart, so remove this service from use
            $this->log->warning("Service '{$service->getProviderName()}' unusable: {$e->getMessage()}", [ 'exception' => $e ]);
        } catch (RangeException $e) {
            // TODO implement backoff-and-retry.
            // TODO $e->getCode() will contain a Retry-After TTL (in seconds), or 0 if exponential backoff is to be implemented
            $this->log->info($e->getMessage(), [ 'exception' => $e ]);
        }
    }


    /**
     * Returns the list of app registrations for the given user, indexed by provider and then app identifier
     *
     * @param Schoolbox\User $user
     * @return Registry[][]
     */
    private function findRegistrationsByUser($user)
    {
        /** @var array<string, array<string, Registry>> $registrations */
        $registrations = [];

        $registryOfUser = RegistryMapper::getInstance()->findByColumn('user_id', $user->getId());

        //Should only attempt to check if the recipient is a parent and has external id
        if ($user->getRole()->isParent() && !!$user->getExternalId()) {
            //Check if user should receive notifications through TASS Orbit
            $registryOfUser = $this->appendTassOrbit($registryOfUser, $user);
        }

        foreach ($registryOfUser as $registration) {
            $srv = $registration->getService();

            // by-reference for convenience
            if ($srv instanceof Service) {
                $p =& $registrations[$srv->getProviderName()][$srv->getAppIdentifier()];
            }

            if (empty($p)) {
                $p['service'] = $srv;
                $p['registrations'] = new GenericCollection(Registry::class);
            }

            $p['registrations']->add($registration);
        }

        return $registrations;
    }


    /**
     * @param Service $service
     * @return ServiceInterface
     * @throws Mapper\Exception|FilesystemException
     */
    public function getService(Service $service)
    {
        $provider   = $service->getProviderName();
        $identifier = $service->getAppIdentifier();

        if (empty($this->serviceCache[$provider][$identifier])) {
            $key = $service->getKey();

            if ($this->shouldUseTestService()) {
                // ignore the provider from the DB and just use the mock
                return new Cloud\Test\TestService($this->registryMapper, $identifier);
            }

            switch ($provider) {
                case Registry::PROVIDER_APPLE:
                    $storageObj = $service->getCertificate();
                    if (!$storageObj instanceof Storage\Model) {
                        // invalid PEM reference
                        throw new Mapper\Exception('No PEM certificate given for APNS service.');
                    }

                    return new Cloud\Apple\ApnService(
                        $this->registryMapper,
                        $identifier,
                        $this->appTitle,
                        $this->getPemPath($storageObj),
                        $key,
                    );

                case Registry::PROVIDER_GOOGLE:
                    return new Cloud\Google\GCM(
                        $this->registryMapper,
                        $identifier,
                        $key,
                        $this->appTitle
                    );

                case Registry::PROVIDER_DIGISTORM:
                    return new Cloud\Digistorm\Service(
                        $this->registryMapper,
                        $identifier,
                        $key,
                        $this->appTitle
                    );

                case Registry::PROVIDER_FIREBASE:
                    return new Cloud\Firebase\Service(
                        $this->registryMapper,
                        $identifier,
                        $key,
                        $this->appTitle,
                    );
                case Registry::PROVIDER_TASS_ORBIT:
                    return new Cloud\TassOrbit\Service(
                        $this->registryMapper,
                        $identifier,
                        $key,
                        $this->configLoader
                    );
            }

            throw new Mapper\Exception(
                "Invalid provider: must be one of APNS, GCM, " . "
                Digistorm, Firebase or TASS Orbit: '$provider' given."
            );
        }

        return $this->serviceCache[$provider][$identifier];
    }


    /**
     * Return the path APNS should use for the PEM file held by the given storage object
     *
     * @param Storage\Model $storage
     * @return string
     * @throws FilesystemException
     */
    private function getPemPath(Storage\Model $storage)
    {
        $hash = $storage->getHash();

        // since this is on-construction, check the file in play and see if it needs updating
        if ($this->apnsCertRequiresUpdate($hash)) {
            // copy the cert from storage
            $fp = fopen($this->getTempCertFilename($hash), 'wb');
            stream_copy_to_stream($this->filesystemProxy->readStream($storage), $fp);
            fclose($fp);
        }

        return $this->getTempCertFilename($hash);
    }


    /**
     * Verify whether or not the given PEM hash requires update on-disk
     *
     * @param string $fileHash
     * @return bool
     */
    private function apnsCertRequiresUpdate($fileHash)
    {
        $tempCert = $this->getTempCertFilename($fileHash);

        if (!is_readable($tempCert)) {
            return true;
        }
        if (!filesize($tempCert)) {
            return true;
        }
        if (sha1_file($tempCert) !== $fileHash) {
            return true;
        }

        return false;
    }


    /**
     * Return a consistent temp-file path for the given hash
     *
     * @param string $fileHash
     * @return string
     */
    private function getTempCertFilename($fileHash)
    {
        return sys_get_temp_dir() . "/{$fileHash}.pem";
    }


    /**
     * @return bool
     */
    private function shouldUseTestService()
    {
        // Is this always using the production or test service?
        if ($this->serviceType === self::SERVICE_TYPE_PRODUCTION) {
            return false;
        }
        if ($this->serviceType === self::SERVICE_TYPE_TEST) {
            return true;
        }

        // use test service in development and staging always
        if ($this->serverInstance->isDevelopment() || $this->serverInstance->isStaging()) {
            return true;
        }

        return false;
    }

    /**
     * @param Collection|Registry[]         $registryOfUser
     * @param Schoolbox\User                $user
     * @return Collection|Registry[]
     */
    private function appendTassOrbit(Collection|array $registryOfUser, Schoolbox\User $user): Collection|array
    {
        //If the TASS Orbit is set up as delivery provider, append it to the list of registry
        try {
            $tassOrbitService =
                (ServiceMapper::getInstance())
                    ->findOneByColumn(
                        'provider',
                        Registry::PROVIDER_TASS_ORBIT
                    );

            $tass_registry = new Registry();
            //Mocking registry to send to notifications
            $tass_registry
                ->setUser($user)
                ->setToken(uniqid('tass_orbit_'))
                ->setService(
                    $tassOrbitService
                );

            $registryOfUser->add($tass_registry);
        } catch (Mapper\Exception\NotFound $e) {
            //TASS Orbit is not set up as delivery provider. Skip.
        } catch (Mapper\Exception $e) {
            $this->log->warning($e->getMessage(), [ 'exception' => $e ]);
        }

        return $registryOfUser;
    }
}
