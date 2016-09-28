<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel;

use Alaress_Date_Format;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task\Model as Task;

/**
 * Tests for the assessment data model translator.
 */
class TranslatorTest extends \PHPUnit_Framework_TestCase
{
    use TestTrait;

    /**
     * Test translation of generic assessment fields
     */
    public function testTranslateGenericFieldsToDataModel()
    {
        $assessment = (new Task())
            ->setId(32)
            ->setCreator($this->teacher1)
            ->setPublishAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setOpenAt(new Alaress_Date_Format('2016-10-16 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDueAt(new Alaress_Date_Format('2016-10-18 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setCloseAt(new Alaress_Date_Format('2016-10-23 13:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new Alaress_Date_Format('2016-10-15 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setDeletedAt(new Alaress_Date_Format('2016-10-16 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
            ->setTitle('Mr Test Task сказать')
            ->setDescription('Blaargh ничего')
            ->setWeight(1000);

        $assessmentModel = $this->translator->translateToDataModel($assessment, 3)->getAssessmentModel();

        $this->assertEquals(32, $assessmentModel->getId());
        $this->assertEquals(3, $assessmentModel->getSequence());
        $this->assertEquals('2016-10-14 12:00:00', $assessmentModel->getPublishDate()->getDatabase());
        $this->assertEquals('2016-10-16 12:00:00', $assessmentModel->getOpenDate()->getDatabase());
        $this->assertEquals('2016-10-18 12:00:00', $assessmentModel->getDueDate()->getDatabase());
        $this->assertEquals('2016-10-23 13:00:00', $assessmentModel->getCloseDate()->getDatabase());
        $this->assertEquals('2016-10-14 12:00:00', $assessmentModel->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-10-15 12:00:00', $assessmentModel->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-10-16 12:00:00', $assessmentModel->getDeletedAt()->getDatabase());
        $this->assertEquals('Mr Test Task сказать', $assessmentModel->getTitle());
        $this->assertEquals('Blaargh ничего', $assessmentModel->getDescription());
        // Tasks are always weightless, regardless of what the assessment says
        $this->assertEquals(0, $assessmentModel->getWeight());
    }

    /**
     * Test translating generic assessment data model fields to the domain object.
     */
    public function testTranslateGenericFieldsToDomainModel()
    {
        $dataModel = (new TaskModel())
            ->setId(15)
            ->setAssessmentModel(
                (new AssessmentModel())
                    ->setId(401)
                    ->setTitle('Some assessment\'s title')
                    ->setDescription('Blargh')
                    ->setWeight(51)
                    ->setPublishDate(new Alaress_Date_Format('2016-10-10 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                    ->setOpenDate(new Alaress_Date_Format('2016-10-11 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                    ->setDueDate(new Alaress_Date_Format('2016-10-12 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                    ->setCloseDate(new Alaress_Date_Format('2016-10-13 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                    ->setCreatedAt(new Alaress_Date_Format('2016-10-14 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                    ->setUpdatedAt(new Alaress_Date_Format('2016-10-15 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
                    ->setDeletedAt(new Alaress_Date_Format('2016-10-16 12:00:00', Alaress_Date_Format::DATABASE_FORMAT))
        );

        /** @var IAssessable $assessment */
        $assessment = $this->translator->translateToDomainModel($dataModel);

        $this->assertEquals(401, $assessment->getId());
        $this->assertEquals('Some assessment\'s title', $assessment->getTitle());
        $this->assertEquals('Blargh', $assessment->getDescription());
        // Tasks are always weightless, regardless of what the assessment says
        $this->assertEquals(0, $assessment->getWeight());
        $this->assertEquals('2016-10-10 12:00:00', $assessment->getPublishAt()->getDatabase());
        $this->assertEquals('2016-10-11 12:00:00', $assessment->getOpenAt()->getDatabase());
        $this->assertEquals('2016-10-12 12:00:00', $assessment->getDueAt()->getDatabase());
        $this->assertEquals('2016-10-13 12:00:00', $assessment->getCloseAt()->getDatabase());
        $this->assertEquals('2016-10-14 12:00:00', $assessment->getCreatedAt()->getDatabase());
        $this->assertEquals('2016-10-15 12:00:00', $assessment->getUpdatedAt()->getDatabase());
        $this->assertEquals('2016-10-16 12:00:00', $assessment->getDeletedAt()->getDatabase());
    }
}
