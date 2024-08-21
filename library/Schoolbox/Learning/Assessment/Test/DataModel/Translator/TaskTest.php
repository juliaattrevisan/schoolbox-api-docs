<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel\Translator;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Test\DataModel\TestTrait;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

/**
 * Tests for the assessment data model translator (tasks only).
 */
class TaskTest extends TestCase
{
    use TestTrait;

    /**
     * Test translating a task
     */
    public function testTranslateToDataModel()
    {
        $task = (new Task())
            // Generic fields
            ->setId(32)
            ->setContext((new Folder())->setId(1))
            ->setCreator(self::getTeacher1())
            ->setPublishAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new Alaress_Date_Format('2016-10-16 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new Alaress_Date_Format('2016-10-18 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new Alaress_Date_Format('2016-10-23 13:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setTitle('Mr Test Task сказать')
            // Description is ignored for tasks
            ->setDescription('Blaargh ничего')
            // Tasks are always weight 0, so this doesn't matter
            ->setWeight(1000)
            // Specific fields
            ->setUrl('https://www.something.com/')
            ->setIsSelfAssessable(true)
        ;

        /** @var TaskModel $taskModel */
        $taskModel = self::getTranslator()->translateToDataModel($task);

        // Test type
        $this->assertInstanceOf(TaskModel::class, $taskModel);

        // Test task-specific fields
        $this->assertEquals(3, $taskModel->getOwner()->getId());
        $this->assertEquals('https://www.something.com/', $taskModel->getLink());
        $this->assertEquals(false, $taskModel->getRequiresConfirmation());
    }


    /**
     * Assert that translating a domain model to a data model, then back to a
     * domain model returns the original domain model.
     */
    public function testTranslateDomainModelBackToDataModel()
    {
        $taskDomainModel = (new Task())
            ->setId(908)
            ->setContext((new Folder())->setId(1))
        ;

        $taskDataModel               = self::getTranslator()->translateToDataModel($taskDomainModel);
        $retranslatedTaskDomainModel = self::getTranslator()->translateToDomainModel($taskDataModel);
        $this->assertSame($taskDomainModel, $retranslatedTaskDomainModel);
    }


    /**
     * Assert that null domain model can be translated to a data model.
     */
    public function testTranslateNullDomainModelToDataModel()
    {
        $nullTaskDomainModel = (new Task())->setId(null);
        $nullTaskDataModel   = self::getTranslator()->translateToDataModel($nullTaskDomainModel);
        $this->assertNull($nullTaskDataModel->getId());
    }


    /**
     * Data provider for testTranslateToDomainModel
     *
     * @return array
     */
    public static function getTestTranslateToDomainModelData()
    {
        // Instance vars defined in setUp() aren't available to data providers
        // I am not sure if this is a bug.
        $assessmentModel = (new AssessmentModel())
            ->setId(1)
            ->setAssessmentContext((new Folder())->setId(1));
        return [
            [
                (new TaskModel())
                    ->setOwner((new User())->setId(3))
                    ->setAssessmentModel($assessmentModel)
                    ->setLink('https://www.google.com.au/')
                    ->setRequiresConfirmation(false),
                'https://www.google.com.au/',
                true,
                3
            ],
            [
                (new TaskModel())
                    ->setOwner((new User())->setId(151))
                    ->setAssessmentModel($assessmentModel)
                    ->setLink(null)
                    ->setRequiresConfirmation(true),
                null,
                false,
                151
            ],
        ];
    }

    /**
     * Test translating task-specific data model fields
     *
     *
     * @param TaskModel $taskModel
     * @param string    $link
     * @param bool      $selfAssessable
     * @param int       $creatorId
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestTranslateToDomainModelData')]
    public function testTranslateToDomainModel(TaskModel $taskModel, $link, $selfAssessable, $creatorId)
    {
        /** @var Task $task */
        $task = self::getTranslator()->translateToDomainModel($taskModel);

        $this->assertInstanceOf(Task::class, $task);
        $this->assertEquals($link, $task->getUrl());
        $this->assertEquals($selfAssessable, $task->isSelfAssessable());
        $this->assertEquals($creatorId, $task->getCreator()->getId());
    }


    /**
     * Assert that translating a data model to a domain model, then back to a
     * data model returns the original data model.
     */
    public function testTranslateDataModelBackToDomainModel()
    {
        $taskDataModel = (new TaskModel())
            ->setId(6789)
            ->setAssessmentModel(
                (new AssessmentModel())
                    ->setAssessmentContext((new Folder())->setId(1))
                    ->setId(33)
            );

        $taskDomainModel           = self::getTranslator()->translateToDomainModel($taskDataModel);
        $retranslatedTaskDataModel = self::getTranslator()->translateToDataModel($taskDomainModel);
        $this->assertSame($taskDataModel, $retranslatedTaskDataModel);
    }


    /**
     * Assert that null data model can be translated to a domain model.
     */
    public function testTranslateNullDataModelToDomainModel()
    {
        $nullTaskDataModel   = (new TaskModel())->setId(null)->setAssessmentModel(new AssessmentModel());
        $nullTaskDomainModel = self::getTranslator()->translateToDomainModel($nullTaskDataModel);
        $this->assertNull($nullTaskDomainModel->getId());
    }
}
