<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Participant\Test;

use PHPUnit\Framework\TestCase;
use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Plan as PlanObject;
use Schoolbox\Assessment\Participant\Plan;
use Schoolbox\Learning\Assessment\Project as ProjectObject;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkObject;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizObject;
use Schoolbox\Learning\Assessment\Task\Model as TaskObject;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectModel;
use Schoolbox\Assessment\Component\SubmissionBox as DueWorkModel;
use Schoolbox\Learning\Quiz\Model as QuizModel;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Learning\Assessment\DataModel as Assessment;

/**
 * Tests the the Plan domain object and data model coalesce methods function as expected.
 */
class CoalesceGetters extends TestCase
{

    /**
     * @param PlanObject          $planObject
     * @param Alaress_Date_Format $publish
     * @param Alaress_Date_Format $open
     * @param Alaress_Date_Format $due
     * @param Alaress_Date_Format $close
     * @dataProvider provider_dates_domainObject
     */
    public function test_dates_DomainObject(
        PlanObject $planObject,
        Alaress_Date_Format $publish,
        Alaress_Date_Format $open,
        Alaress_Date_Format $due,
        Alaress_Date_Format $close
    ): void {
        $this->assertEquals($publish, $planObject->coalescePublishDate());
        $this->assertEquals($open, $planObject->coalesceOpenDate());
        $this->assertEquals($due, $planObject->coalesceDueDate());
        $this->assertEquals($close, $planObject->coalesceCloseDate());
    }


    /**
     * @param Plan\Model               $planModel
     * @param Alaress_Date_Format|null $publish
     * @param Alaress_Date_Format|null $open
     * @param Alaress_Date_Format|null $due
     * @param Alaress_Date_Format|null $close
     * @dataProvider provider_dates_dataModel
     */
    public function test_dates_DataModel(
        Plan\Model $planModel,
        ?Alaress_Date_Format $publish,
        ?Alaress_Date_Format $open,
        ?Alaress_Date_Format $due,
        ?Alaress_Date_Format $close
    ): void {
        $this->assertEquals($publish, $planModel->coalescePublishDate());
        $this->assertEquals($open, $planModel->coalesceOpenDate());
        $this->assertEquals($due, $planModel->coalesceDueDate());
        $this->assertEquals($close, $planModel->coalesceCloseDate());
    }


    /**
     * @param PlanObject  $planObject
     * @param null|string $description
     * @dataProvider provider_description_domainObject
     */
    public function test_description_DomainObject(PlanObject $planObject, ?string $description): void
    {
        $this->assertEquals($description, $planObject->coalesceDescription());
    }


    /**
     * @param Plan\Model  $planModel
     * @param null|string $description
     * @dataProvider provider_description_dataModel
     */
    public function test_description_DataModel(Plan\Model $planModel, ?string $description): void
    {
        $this->assertEquals($description, $planModel->coalesceDescription());
    }


    public function provider_description_domainObject()
    {
        $data = [];

        foreach ($this->getAssessables() as $assessable) {
            $assessable->setDescription(null);
            $planObject = (new PlanObject($assessable))->setDescription(null);
            $data[]     = [$planObject, null];
        }

        foreach ($this->getAssessables() as $assessable) {
            $assessable->setDescription(null);
            $planObject = (new PlanObject($assessable))->setDescription('');
            $data[]     = [$planObject, ''];
        }

        foreach ($this->getAssessables() as $assessable) {
            $assessable->setDescription('');
            $planObject = (new PlanObject($assessable))->setDescription(null);
            $data[]     = [$planObject, ''];
        }

        foreach ($this->getAssessables() as $assessable) {
            $assessable->setDescription('null');
            $planObject = (new PlanObject($assessable))->setDescription(null);
            $data[]     = [$planObject, 'null'];
        }

        foreach ($this->getAssessables() as $assessable) {
            $assessable->setDescription(null);
            $planObject = (new PlanObject($assessable))->setDescription('null');
            $data[]     = [$planObject, 'null'];
        }

        foreach ($this->getAssessables() as $assessable) {
            $assessable->setDescription('string2');
            $planObject = (new PlanObject($assessable))->setDescription('string1');
            $data[]     = [$planObject, 'string1'];
        }

        return $data;
    }


    public function provider_description_dataModel()
    {
        $data = [];

        $assessment = (new Assessment\Model())->setDescription(null);
        $planModel  = (new Plan\Model())->setDescription(null)->setAssessment($assessment);
        $data[]     = [$planModel, null];

        $assessment = (new Assessment\Model())->setDescription(null);
        $planModel  = (new Plan\Model())->setDescription('')->setAssessment($assessment);
        $data[]     = [$planModel, ''];

        $assessment = (new Assessment\Model())->setDescription('');
        $planModel  = (new Plan\Model())->setDescription(null)->setAssessment($assessment);
        $data[]     = [$planModel, ''];

        $assessment = (new Assessment\Model())->setDescription('null');
        $planModel  = (new Plan\Model())->setDescription(null)->setAssessment($assessment);
        $data[]     = [$planModel, 'null'];

        $assessment = (new Assessment\Model())->setDescription(null);
        $planModel  = (new Plan\Model())->setDescription('null')->setAssessment($assessment);
        $data[]     = [$planModel, 'null'];

        $assessment = (new Assessment\Model())->setDescription('string2');
        $planModel  = (new Plan\Model())->setDescription('string1')->setAssessment($assessment);
        $data[]     = [$planModel, 'string1'];

        return $data;
    }


    public function provider_dates_domainObject()
    {
        $data = [];

        foreach ($this->getDates() as $assessmentDate) {
            foreach ($this->getDates() as $planDate) {
                foreach ($this->getAssessables() as $assessable) {
                    if (!$assessmentDate) {
                        continue;
                    }
                    $assessable
                        ->setPublishAt($this->cloneDate($assessmentDate))
                        ->setOpenAt($this->cloneDate($assessmentDate))
                        ->setDueAt($this->cloneDate($assessmentDate))
                        ->setCloseAt($this->cloneDate($assessmentDate));

                    $planObject = (new PlanObject($assessable))
                        ->setPublishDate($this->cloneDate($planDate))
                        ->setOpenDate($this->cloneDate($planDate))
                        ->setDueDate($this->cloneDate($planDate))
                        ->setCloseDate($this->cloneDate($planDate));

                    $data[] = [
                        $planObject,
                        null === $planDate || $planDate->isNull() ? $assessmentDate : $planDate,
                        null === $planDate || $planDate->isNull() ? $assessmentDate : $planDate,
                        null === $planDate || $planDate->isNull() ? $assessmentDate : $planDate,
                        null === $planDate || $planDate->isNull() ? $assessmentDate : $planDate,
                    ];
                }
            }
        }
        return $data;
    }


    public function provider_dates_dataModel()
    {
        $data = [];

        foreach ($this->getDates() as $assessmentDate) {
            foreach ($this->getDates() as $planDate) {
                foreach ($this->getAssessments() as $assessment) {
                    $assessment->setAssessmentModel(new Assessment\Model());
                    $assessment->getAssessmentModel()
                               ->setPublishDate($this->cloneDate($assessmentDate))
                               ->setOpenDate($this->cloneDate($assessmentDate))
                               ->setDueDate($this->cloneDate($assessmentDate))
                               ->setCloseDate($this->cloneDate($assessmentDate));

                    $planModel = (new Plan\Model())
                        ->setAssessment($assessment->getAssessmentModel())
                        ->setPublishDate($this->cloneDate($planDate))
                        ->setOpenDate($this->cloneDate($planDate))
                        ->setDueDate($this->cloneDate($planDate))
                        ->setCloseDate($this->cloneDate($planDate));

                    $data[] = [
                        $planModel,
                        null === $planDate || $planDate->isNull() ? $assessmentDate : $planDate,
                        null === $planDate || $planDate->isNull() ? $assessmentDate : $planDate,
                        null === $planDate || $planDate->isNull() ? $assessmentDate : $planDate,
                        null === $planDate || $planDate->isNull() ? $assessmentDate : $planDate,
                    ];
                }
            }
        }
        return $data;
    }


    /**
     * @param Alaress_Date_Format|null $date
     * @return Alaress_Date_Format|null
     */
    private function cloneDate(?Alaress_Date_Format $date): ?Alaress_Date_Format
    {
        return $date ? clone $date : null;
    }


    /**
     * @return IAssessable[]
     */
    private function getAssessables(): array
    {
        return [
            new ProjectObject(),
            new DueWorkObject(),
            new QuizObject(),
            new TaskObject(),
        ];
    }


    /**
     * @return IAssessableDataModel[]
     */
    private function getAssessments(): array
    {
        return [
            new ProjectModel(),
            new DueWorkModel(),
            new QuizModel(),
            new TaskModel(),
        ];
    }


    /**
     * @return null[]|Alaress_Date_NullDate[]
     */
    private function getDates(): array
    {
        return [
            null,
            new Alaress_Date_NullDate(),
            $this->buildDate('2016-01-01 00:00:00'),
            $this->buildDate('2016-01-01 12:00:00'),
            $this->buildDate('2016-01-01 23:59:59'),
        ];
    }


    /**
     * @param string $dateString
     * @return Alaress_Date_Format
     */
    private function buildDate(string $dateString): Alaress_Date_Format
    {
        return new Alaress_Date_Format($dateString, Alaress_Date_Format::DATABASE_FORMAT);
    }
}
