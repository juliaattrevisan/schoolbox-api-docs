<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel;

use Alaress_Date_Format;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Learning\Assessment\DataModel\Translator;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\User;

/**
 * Tests for the assessment data model translator.
 */
class TranslatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var User
     */
    protected $teacher1;

    /**
     * Set up
     */
    protected function setUp()
    {
        $this->translator = new Translator();

        // Test users
        $this->teacher1 = (new User())
            ->setId(3)
            ->setExternalId(8)
            ->setFirstname('Celia')
            ->setLastname('Glenn')
        ;
    }

    /**
     * Test translating a task
     */
    public function testTranslateTaskToDataModel()
    {
        $task = (new Task())
            ->setId(32)
            ->setCreator($this->teacher1)
            ->setPublishAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new Alaress_Date_Format('2016-10-16 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new Alaress_Date_Format('2016-10-18 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new Alaress_Date_Format('2016-10-23 13:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setTitle('Mr Test Task сказать')
            // Tasks are always weight 0, so this doesn't matter
            ->setWeight(1000)
        ;

        /** @var TaskModel $taskModel */
        $taskModel = $this->translator->translateToDataModel($task, 3);

        $this->assertInstanceOf(TaskModel::class, $taskModel);
        $this->assertEquals(32, $taskModel->getAssessmentModel()->getId());
        $this->assertEquals(3, $taskModel->getAssessmentModel()->getSequence());
        $this->assertEquals(3, $taskModel->getOwner()->getId());
        $this->assertEquals('Mr Test Task сказать', $taskModel->getTitle());
        $this->assertEquals('https://www.something.com/', $taskModel->getLink());
        $this->assertEquals(false, $taskModel->getRequiresConfirmation());
    }
}
