<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Import\Atlas;

use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use GuzzleHttp\Client;
use Karriere\JsonDecoder\JsonDecoder;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper as RubricCriterionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\GroupMapper as RubricGroupMapper;
use Schoolbox\Assessment\Course\Import\Atlas\Api\Bridge as ApiBridge;
use Schoolbox\Learning\Course;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Twig\Error\LoaderError;

class Module extends \Alaress\Module\Module
{
    /** @var string */
    protected $basename = 'courseImportAtlas';


    /**
     * @return void
     * @throws \Schoolbox\Application\Exception\NotFound
     * @throws \InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $staffOnly = $this->getAuthRule('isStaffOrSuper');
        $this->register('GET', '', 'getImport', $staffOnly);
        $this->register('POST', '', 'postImport', $staffOnly);
        $this->register('GET', '/curriculum/list', 'getCurriculumList', $staffOnly, null);
        $this->register('GET', '/curriculum/{curriculumId:\d+}', 'getCurriculum', $staffOnly, null);
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
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'courseSpecMapper'       => $this->getMapperFactory()->getLazy(CourseSpecMapper::class),
                    'unitSpecMapper'         => $this->getMapperFactory()->getLazy(UnitSpecMapper::class),
                    'homepageConfigMapper'   => $this->getMapperFactory()->getLazy(HomepageConfigMapper::class),
                    'homepageTemplateMapper' => $this->getMapperFactory()->getLazy(HomepageTemplateMapper::class),
                    'folderMapper'           => $this->getMapperFactory()->getLazy(FolderMapper::class),
                    'userAccessMapper'       => $this->getMapperFactory()->getLazy(UserAccessMapper::class),
                    'rubricGroupMapper'      => $this->getMapperFactory()->getLazy(RubricGroupMapper::class),
                    'rubricCriterionMapper'  => $this->getMapperFactory()->getLazy(RubricCriterionMapper::class),
                    'access'                 => $injector->lazy(function () { return Access::getInstance(); }),
                    'apiBridge'              => $injector->lazyNew(
                        ApiBridge::class,
                        [
                            'httpClient' => $injector->lazyNew(
                                Client::class,
                                [
                                    'config' => [
                                        'base_uri' => 'https://api.rubicon.com/api/',
                                        'headers'  => [
                                            'Authorization' => 'Bearer ' . $this->getConfig()->get('rubicon_atlas_api_token'),
                                            'Accept'        => 'application/json',
                                        ],
                                    ]
                                ]
                            ),
                            'redisClient' => $injector->lazyGet('redis'),
                            'decoder'     => $injector->lazy(
                                function () {
                                    $decoder = new JsonDecoder(true, true);
                                    $decoder->register(new Api\CurriculumTransformer());
                                    $decoder->register(new Api\TeacherTransformer());
                                    $decoder->register(new Api\UnitTransformer());
                                    $decoder->register(new Api\AssessmentTransformer());
                                    $decoder->register(new Api\StandardTransformer());
                                    $decoder->register(new Api\AttachmentTransformer());
                                    return $decoder;
                                }
                            )
                        ]
                    )
                ]
            )
        );
    }
}
