<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel\Translator;

use Alaress_Date_Format;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Test\DataModel\TestTrait;
use Schoolbox\User;

/**
 * Tests for the assessment data model translator (tasks only).
 */
class TaskTest extends \PHPUnit_Framework_TestCase
{
    use TestTrait;

    /**
     * Test translating a task
     */
    public function testTranslateTaskToDataModel()
    {
        $task = (new Task())
            // Generic fields
            ->setId(32)
            ->setCreator($this->teacher1)
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
            ->setLink('https://www.something.com/')
            ->setSelfMarkable(true)
        ;

        /** @var TaskModel $taskModel */
        $taskModel = $this->translator->translateToDataModel($task, 3);

        // Test type
        $this->assertInstanceOf(TaskModel::class, $taskModel);

        // Test task-specific fields
        $this->assertEquals(3, $taskModel->getOwner()->getId());
        $this->assertEquals('https://www.something.com/', $taskModel->getLink());
        $this->assertEquals(false, $taskModel->getRequiresConfirmation());
    }

    /**
     * Data provider for testTranslateTaskToDomainModel
     *
     * @return array
     */
    public function getTestTranslateTaskToDomainModelData()
    {
        // Instance vars defined in setUp() aren't available to data providers
        // I am not sure if this is a bug.
        return [
            [
                (new TaskModel())
                    ->setOwner((new User())->setId(3))
                    ->setAssessmentModel((new AssessmentModel()))
                    ->setLink('https://www.google.com.au/')
                    ->setRequiresConfirmation(false),
                'https://www.google.com.au/',
                true,
                3
            ],
            [
                (new TaskModel())
                    ->setOwner((new User())->setId(151))
                    ->setAssessmentModel((new AssessmentModel()))
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
     * @dataProvider getTestTranslateTaskToDomainModelData
     *
     * @param TaskModel $taskModel
     * @param string    $link
     * @param bool      $selfMarkable
     * @param int       $creatorId
     */
    public function testTranslateTaskToDomainModel(TaskModel $taskModel, $link, $selfMarkable, $creatorId)
    {
        /** @var Task $task */
        $task = $this->translator->translateToDomainModel($taskModel);

        $this->assertInstanceOf(Task::class, $task);
        $this->assertEquals($link, $task->getLink());
        $this->assertEquals($selfMarkable, $task->getSelfMarkable());
        $this->assertEquals($creatorId, $task->getCreator()->getId());
    }
}
