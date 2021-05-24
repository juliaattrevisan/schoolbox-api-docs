<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas;

use Component_InstanceMapper;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Assessment\Course\Import\Atlas\Api\Attachment;
use Schoolbox\Assessment\Course\Import\Atlas\AttachmentHandler;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Mapper as RubricGroupMapper;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as RubricCriterionMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Course;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecMapper;
use Schoolbox\MapperFactory;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\User;

/**
 * Factory for the Atlas importer.
 */
class ImporterFactory
{
    /**
     * @var User
     */
    private $user;


    /**
     * @var IConfigLoader
     */
    private $config;


    /**
     * @var MapperFactory
     */
    private $mapperFactory;


    /**
     * @var AssessmentDataSource
     */
    private $assessmentDataSource;


    /**
     * @var RubricDataSource
     */
    private $rubricMapper;


    /**
     * Constructor
     *
     * @param User                 $user
     * @param IConfigLoader        $config
     * @param MapperFactory        $mapperFactory
     * @param AssessmentDataSource $assessmentDataSource
     * @param RubricDataSource     $rubricMapper
     */
    public function __construct(
        User $user,
        IConfigLoader $config,
        MapperFactory $mapperFactory,
        AssessmentDataSource $assessmentDataSource,
        RubricDataSource $rubricMapper
    ) {
        // I'm aware that passing a DI container (which the mapper factory
        // effectively is) is an anti-pattern, but is it less of one than
        // Mapper::getInstance?
        $this->user                 = $user;
        $this->config               = $config;
        $this->mapperFactory        = $mapperFactory;
        $this->assessmentDataSource = $assessmentDataSource;
        $this->rubricMapper         = $rubricMapper;
    }


    /**
     * Build the Atlas importer.
     *
     * @return Importer
     */
    public function build(): Importer
    {
        $importer = new Importer(
            $this->user,
            $this->config,
            $this->mapperFactory->get(FolderMapper::class),
            $this->mapperFactory->get(HomepageConfigMapper::class),
            $this->mapperFactory->get(HomepageTemplateMapper::class),
            $this->mapperFactory->get(CourseConfigMapper::class),
            $this->mapperFactory->get(CourseSpecMapper::class),
            $this->mapperFactory->get(UnitSpecMapper::class),
            $this->mapperFactory->get(RoleAccessMapper::class),
            $this->rubricMapper,
            $this->mapperFactory->get(RubricGroupMapper::class),
            $this->mapperFactory->get(RubricCriterionMapper::class),
            $this->assessmentDataSource,
            $this->mapperFactory->get(NodeMapper::class),
            $this->mapperFactory->get(ErrorMapper::class)
        );
        return $importer
            ->registerAttachmentHandler(
                new AttachmentHandler\Link(
                    $this->mapperFactory->get(LinkMapper::class)
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
                    $this->mapperFactory->get(TextboxMapper::class),
                    $this->mapperFactory->get(Component_InstanceMapper::class)
                ),
                [
                    Attachment::ATTACHMENT_TYPE_GOOGLE_DRIVE,
                    Attachment::ATTACHMENT_TYPE_ONEDRIVE,
                ]
            );
    }
}
