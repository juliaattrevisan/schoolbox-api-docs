<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeGroupMapper;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Mapper as CapabilityMapper;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as IndicatorMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Twig\Error\Loader;
use Twig\Error\LoaderError;

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
        $this->register('POST', '/determineMarkInvalidation', 'determineMarkInvalidation', $permission);
        $this->register('POST', '/import/{storageTuple}', 'import', $permission, $filePreload);
    }


    /**
     * @param IChainLoader $templateLoader
     * @throws LoaderError
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
            $injector->lazyNew(
                Controller::class,
                [
                'mapper'               => $this->getMapperFactory()->getLazy(RubricDataSource::class),
                'assessmentDataSource' => $injector->lazyGet('assessmentDataSource'),
                'folderMapper'         => $this->getMapperFactory()->getLazy(FolderMapper::class),
                'groupMapper'          => $this->getMapperFactory()->getLazy(CapabilityMapper::class),
                'criterionMapper'      => $this->getMapperFactory()->getLazy(IndicatorMapper::class),
                'rosettaStone'         => $injector->lazyGet('l10n'),
                'markTypeMapper'       => $this->getMapperFactory()->getLazy(MarkTypeMapper::class),
                'gradeGroupMapper'     => $this->getMapperFactory()->getLazy(GradeGroupMapper::class),
                ]
            )
        );
    }
}
