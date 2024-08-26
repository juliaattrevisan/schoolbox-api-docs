<?php

namespace Alaress\Async\Notification\Cloud\TassOrbit;

use Psr\Log\LoggerInterface;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception;
use Schoolbox\MobileApp\Registry;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\MobileApp\ServiceMapper;
use Schoolbox\User;

class Helper
{
    /**
     * Register and append TASS Orbit service to user for the first
     * time if user is eligible
     *
     * @param Collection|Registry[] $registryOfUser
     * @param User                  $user
     * @param LoggerInterface       $log
     * @param RegistryMapper        $registryMapper
     * @return Collection|Registry[]
     */
    public static function appendTassOrbit(
        LoggerInterface $log,
        RegistryMapper $registryMapper,
        Collection|array $registryOfUser,
        User $user,
    ): Collection|array {

        if (!self::shouldRegisterService($registryOfUser, $user)) {
            return $registryOfUser;
        }

        //If the TASS Orbit is set up as delivery provider, append it to the list of registry
        try {
            $tassOrbitServices =
                (ServiceMapper::getInstance())
                    ->findByColumn(
                        'provider',
                        Registry::PROVIDER_TASS_ORBIT
                    );

            $now = \Alaress_Date_Format::now();

            $existingRegistry = Collection::arrayColumn($registryOfUser, 'token');

            foreach ($tassOrbitServices as $tassOrbitService) {
                $newToken = 'tass_orbit_' . $user->getId() . '@' . $tassOrbitService->getAppIdentifier();

                //Already registered. Skip.
                if (in_array($newToken, $existingRegistry)) {
                    continue;
                }

                $tass_registry = new Registry();
                //Mocking registry to send to notifications
                $tass_registry
                    ->setUser($user)
                    ->setToken($newToken)
                    ->setService($tassOrbitService)
                    ->setDeviceId(Registry::PROVIDER_TASS_ORBIT)
                    ->setCreatedAt($now)
                    ->setTouchedAt($now);
                /**
                 * Registering to 'mobile_app_registry', TASS handles the registering on their
                 * end, and that supposedly they don't get de-registered, we will leave the
                 * cleaning-up to the cron job in 'cleanUpAbandoned'
                 */
                $registryMapper->save($tass_registry);
                $registryOfUser->add($tass_registry);
            }
        } catch (Exception\NotFound $e) {
            //TASS Orbit is not set up as delivery provider. Skip.
        } catch (Exception $e) {
            $log->warning($e->getMessage(), [ 'exception' => $e ]);
        }

        return $registryOfUser;
    }

    /**
     * Check if TASS already in the array of Registry and that the recipient is eligible
     *
     * @param Collection|array $registryOfUser
     * @param User             $user
     * @return bool
     */
    private static function shouldRegisterService(Collection|array $registryOfUser, User $user): bool
    {
        return
            //Should only attempt to check if the recipient is a parent and has external id
            ($user->getRole()->isParent() && !!$user->getExternalId()) &&
            //If there are any new TASS delivery provider should add
            (ServiceMapper::getInstance())
                ->countByColumn('provider', Registry::PROVIDER_TASS_ORBIT)
            > count(
                array_filter(
                    Collection::arrayColumn($registryOfUser, 'service'),
                    function ($value) {
                        return
                            $value->getProviderName() === Registry::PROVIDER_TASS_ORBIT;
                    }
                )
            );
    }
}
