<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Import\Photos;

use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Alaress\Twig\Loader\IChainLoader;
use Psr\Container\ContainerInterface;
use Schoolbox\Resource\Storage;
use Schoolbox\UserMapper;

/**
 * Class Module
 *
 * Import portrait pictures for users
 *
 * @package Schoolbox\Admin\Import\Photos
 */
class Module extends BaseModule
{

    /**
     * @var string
     */
    protected $basename = 'adminImportPhotos';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Only supers can get here
         */
        $superuserOnly = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\IsSuperuser()
            );
        });

        $this->register('GET', '/upload', 'getIndex', $superuserOnly);
        $this->register('POST', '/upload', 'postUpload', $superuserOnly);
        $this->register('POST', '/delete', 'postDelete', $superuserOnly);
    }


    /**
     * @inheritdoc
     */
    public function buildController(ContainerInterface $container): ?Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Storage\Mapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getConfig(),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
