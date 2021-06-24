<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Audience;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\HasServicePermission;
use Alaress\Module as AlaressModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Schoolbox\Pastoral;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Alaress\Twig\Loader\IChainLoader;
use InvalidArgumentException;
use Alaress\Authorizer\Rule;

class Module extends AlaressModule\Module
{
    protected $basename = 'pastoralAudience';

    /**
     * {@inheritdoc}
     */
    protected function registerRoutes(): void
    {
        $loggedInAndPastoralWritePerm = new Rule\LazyCallback(function () {
            return new HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_WRITE);
        });

        // Don't permit creation of default audience for a PastoralType if it has SubTypes
        $pastoralWriteAndTypeHasSubTypes = new Rule\LazyCallback(function () use ($loggedInAndPastoralWritePerm) {
            return new Rule\All(
                $loggedInAndPastoralWritePerm,
                new Rule\Callback(function (array $params = []) {
                    if (!\array_key_exists('type', $params)) {
                        throw new InvalidArgumentException("No 'type' found in route params");
                    }
                    /** @var Pastoral\Type\Model $type */
                    $type = $params['type'];
                    if ($type->getSubTypes()->count()) {
                        throw new Unauthorized('Cannot set default audience rules for a PastoralType than has SubTypes');
                    }
                })
            );
        });

        $this->registerRoutesArray([
            'GET'  => [
                ''                            => ['getAudiences'              => $loggedInAndPastoralWritePerm],
                '/type/{typeId}/modify'       => ['getAudiencesTypeModify'    => $pastoralWriteAndTypeHasSubTypes],
                '/subtype/{subtypeId}/modify' => ['getAudiencesSubTypeModify' => $loggedInAndPastoralWritePerm],
            ],
            'POST' => [
                '/type/{typeId}/modify'       => ['postAudienceTypeModify'    => $pastoralWriteAndTypeHasSubTypes],
                '/subtype/{subtypeId}/modify' => ['postAudienceSubTypeModify' => $loggedInAndPastoralWritePerm],
            ],
        ]);
    }


    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException If invalid mapper class specified
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $mappers = [
            'userMapper'             => $this->getMapperFactory()->getLazy(UserMapper::class),
            'pastoralGroupMapper'    => $this->getMapperFactory()->getLazy(Pastoral\Group\Mapper::class),
            'pastoralAudienceMapper' => $this->getMapperFactory()->getLazy(Pastoral\Audience\Mapper::class),
            'roleMapper'             => $this->getMapperFactory()->getLazy(RoleMapper::class),
            'pastoralTypeMapper'     => $this->getMapperFactory()->getLazy(Pastoral\Type\Mapper::class),
        ];

        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class, $mappers));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher): void
    {
        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) {
                $returnArray = [];

                if (\array_key_exists('typeId', $params)) {
                    if (($params['type'] ?? null) instanceof Pastoral\Type\Model) {
                        $returnArray['type'] = $params['type'];
                    } else {
                        $typeMapper          = $this->getMapperFactory()->get(Pastoral\Type\Mapper::class);
                        $returnArray['type'] = $typeMapper->findById($params['typeId']);
                    }
                }

                if (\array_key_exists('subtypeId', $params)) {
                    if (($params['subType'] ?? null) instanceof Pastoral\SubType\Model) {
                        $subType = $params['subType'];
                    } else {
                        $subTypeMapper = $this->getMapperFactory()->get(Pastoral\SubType\Mapper::class);
                        $subType       = $subTypeMapper->findById($params['subtypeId']);
                    }
                    // Need to ensure the subType's type is hydrated
                    /** @var Pastoral\SubType\Model $subType */
                    if (null === $subType->getPastoralType()->getName()) {
                        /** @var Pastoral\Type\Mapper $typeMapper */
                        $typeMapper = $this->getMapperFactory()->get(Pastoral\Type\Mapper::class);
                        $subType->setPastoralType($typeMapper->findById($subType->getPastoralType()->getId()));
                    }
                    $returnArray['subType'] = $subType;
                }

                return $returnArray;
            }
        );
    }
}
