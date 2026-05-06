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
use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeGroupMapper;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Mapper as CapabilityMapper;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as IndicatorMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
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
                /* TODO Replace with Learning/Authorizer/Rule/IsInstructorOrHasHomepageAccess() I suppose
                 */
                // TODO: instructors (non-staff) mangaging rubrics they have acess to? otherwise they hit a 403
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
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(RubricDataSource::class),
            $this->injector->get('assessmentDataSource'),
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(CapabilityMapper::class),
            $this->getMapperFactory()->get(IndicatorMapper::class),
            $this->injector->get('l10n'),
            $this->getMapperFactory()->get(MarkTypeMapper::class),
            $this->getMapperFactory()->get(GradeGroupMapper::class),
        );
    }
}
