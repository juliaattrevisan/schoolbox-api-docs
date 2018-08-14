<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Import\Atlas;

use Alaress\Authorizer\Rule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Component_InstanceMapper;
use GuzzleHttp\Client;
use Karriere\JsonDecoder\JsonDecoder;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper as RubricCriterionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\GroupMapper as RubricGroupMapper;
use Schoolbox\Assessment\Course\Import\Atlas\Api\Attachment;
use Schoolbox\Assessment\Course\Import\Atlas\Api\Bridge as ApiBridge;
use Schoolbox\Assessment\Course\Import\Atlas\Api\JsonDecoderFactory;
use Schoolbox\Assessment\Course\Import\Atlas\AttachmentHandler;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\Learning\Course;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Resource\LinkMapper;
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
        $staffOnly = new Rule\All(
            new Rule\IsConfigOn('rubicon_atlas_api_token', $this->getConfig()),
            $this->getAuthRule('isStaffOrSuper')
        );
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
                    'folderMapper'     => $this->getMapperFactory()->getLazy(FolderMapper::class),
                    'courseSpecMapper' => $this->getMapperFactory()->getLazy(CourseSpecMapper::class),
                    'apiBridge'        => $injector->lazyNew(
                        ApiBridge::class,
                        [
                            'httpClient'  => $injector->lazyNew(
                                Client::class,
                                [
                                    'config' => [
                                        'base_uri' => 'https://api.rubicon.com/api/',
                                        'headers'  => [
                                            'Authorization' => 'Bearer ' . $this->getConfig()->get('rubicon_atlas_api_token'),
                                            'Accept'        => 'application/json',
                                        ],
                                    ],
                                ]
                            ),
                            'redisClient' => $injector->lazyGet('redis'),
                            'decoder'     => $injector->lazy(
                                function () {
                                    return (new JsonDecoderFactory())->build();
                                }
                            ),
                        ]
                    ),
                    'importer'         => $injector->lazy(
                        function () use ($injector) {
                            $importer = new Importer(
                                $injector->get('user'),
                                $this->getConfig(),
                                $this->getMapperFactory()->get(FolderMapper::class),
                                $this->getMapperFactory()->get(HomepageConfigMapper::class),
                                $this->getMapperFactory()->get(HomepageTemplateMapper::class),
                                $this->getMapperFactory()->get(CourseSpecMapper::class),
                                $this->getMapperFactory()->get(UnitSpecMapper::class),
                                Access::getInstance(),
                                $this->getMapperFactory()->get(UserAccessMapper::class),
                                $this->getMapperFactory()->get(RubricGroupMapper::class),
                                $this->getMapperFactory()->get(RubricCriterionMapper::class),
                                $injector->get('assessmentDataSource'),
                                $this->getMapperFactory()->get(NodeMapper::class)
                            );
                            return $importer
                                ->registerAttachmentHandler(
                                    new AttachmentHandler\Link(
                                        $this->getMapperFactory()->get(LinkMapper::class)
                                    ),
                                    [
                                        Attachment::ATTACHMENT_TYPE_LINK,
                                        // TODO consider handling these with the file handler
                                        // Can't do this now, as I can't pass the auth challenge
                                        Attachment::ATTACHMENT_TYPE_FILE,
                                        Attachment::ATTACHMENT_TYPE_IMAGE,
                                        Attachment::ATTACHMENT_TYPE_LESSON,
                                    ]
                                )
                                ->registerAttachmentHandler(
                                    new AttachmentHandler\Embed(
                                        $this->getMapperFactory()->get(TextboxMapper::class),
                                        $this->getMapperFactory()->get(Component_InstanceMapper::class)
                                    ),
                                    [
                                        Attachment::ATTACHMENT_TYPE_GOOGLE_DRIVE,
                                        Attachment::ATTACHMENT_TYPE_ONEDRIVE,
                                    ]
                                );
                        }
                    ),
                ]
            )
        );
    }
}
