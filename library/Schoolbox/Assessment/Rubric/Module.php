<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Role;
use Twig_Error_Loader;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'rubric';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $permission  = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF)
            )
        );
        $filePreload = function ($params) {
            return [
                'file' => (new CollectorFactory())->build([$params['storageTuple']])->getFirst(),
            ];
        };

        // Download/upload rubric actions
        $this->register('POST', '', 'create', $permission);
        $this->register('POST', '/csv', 'download', $permission);
        $this->register('POST', '/import/{storageTuple}', 'import', $permission, $filePreload);
    }


    /**
     * @param IChainLoader $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class),
            [
                'mapper' => $this->getMapperFactory()->getLazy(RubricMapper::class),
                'assessmentDataSource' => $injector->lazyGet('assessmentDataSource'),
            ]
        );
    }
}
