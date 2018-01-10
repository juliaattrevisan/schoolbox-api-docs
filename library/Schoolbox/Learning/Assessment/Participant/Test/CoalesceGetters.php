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
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan as PlanObject;
use Schoolbox\Learning\Assessment\Project as ProjectObject;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkObject;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizObject;
use Schoolbox\Learning\Assessment\Task\Model as TaskObject;
use Schoolbox\User;

/**
 * Tests the the Plan domain object and data model coalesce methods function as expected.
 */
class CoalesceGetters extends TestCase
{

    /**
     * @param Participant $participant
     * @param Alaress_Date_Format $publish
     * @param Alaress_Date_Format $open
     * @param Alaress_Date_Format $due
     * @param Alaress_Date_Format $close
     * @dataProvider provider_dates_domainObject
     */
    public function test_dates_DomainObject(
        Participant $participant,
        Alaress_Date_Format $publish,
        Alaress_Date_Format $open,
        Alaress_Date_Format $due,
        Alaress_Date_Format $close
    ): void {
        $this->assertEquals($publish, $participant->getPublishDate());
        $this->assertEquals($open, $participant->getOpenDate());
        $this->assertEquals($due, $participant->getDueDate());
        $this->assertEquals($close, $participant->getCloseDate());
    }



    /**
     * @param Participant $participant
     * @param null|string $description
     * @dataProvider provider_description_domainObject
     */
    public function test_description_DomainObject(Participant $participant, ?string $description): void
    {
        $this->assertEquals($description, $participant->getDescription());
    }


    public function provider_description_domainObject()
    {
        $data = [];

        foreach ($this->getAssessables() as $assessable) {
            $assessable->setDescription(null);
            $planObject = (new PlanObject($assessable))->setDescription(null);
            $data[]     = [new Participant(new User(), $planObject), null];
        }

        foreach ($this->getAssessables() as $assessable) {
            $assessable->setDescription(null);
            $planObject = (new PlanObject($assessable))->setDescription('');
            $data[]     = [new Participant(new User(), $planObject), ''];
        }

        foreach ($this->getAssessables() as $assessable) {
            $assessable->setDescription('');
            $planObject = (new PlanObject($assessable))->setDescription(null);
            $data[]     = [new Participant(new User(), $planObject), ''];
        }

        foreach ($this->getAssessables() as $assessable) {
            $assessable->setDescription('null');
            $planObject = (new PlanObject($assessable))->setDescription(null);
            $data[]     = [new Participant(new User(), $planObject), 'null'];
        }

        foreach ($this->getAssessables() as $assessable) {
            $assessable->setDescription(null);
            $planObject = (new PlanObject($assessable))->setDescription('null');
            $data[]     = [new Participant(new User(), $planObject), 'null'];
        }

        foreach ($this->getAssessables() as $assessable) {
            $assessable->setDescription('string2');
            $planObject = (new PlanObject($assessable))->setDescription('string1');
            $data[]     = [new Participant(new User(), $planObject), 'string1'];
        }

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
                        new Participant(new User(), $planObject),
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
