<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Participant\Test;

use PHPUnit\Framework\TestCase;
use Alaress_Date_Format;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Plan as PlanObject;
use Schoolbox\Learning\Assessment\Project\ResultCache;
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
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDatesDomainObject')]
    public function testDatesDomainObject(
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
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDescriptionDomainObject')]
    public function testDescriptionDomainObject(Participant $participant, ?string $description): void
    {
        $this->assertEquals($description, $participant->getDescription());
    }


    public static function providerDescriptionDomainObject()
    {
        $resultCacheMapper = ResultCache\Mapper::getInstance();

        $data = [];

        foreach (self::getAssessables() as $assessable) {
            $assessable->setDescription(null);
            $planObject = (new PlanObject($assessable))->setDescription(null)->setIsDynamic(false);
            $data[]     = [new Participant(new User(), $planObject, $resultCacheMapper), null];
        }

        foreach (self::getAssessables() as $assessable) {
            $assessable->setDescription(null);
            $planObject = (new PlanObject($assessable))->setDescription('')->setIsDynamic(false);
            $data[]     = [new Participant(new User(), $planObject, $resultCacheMapper), ''];
        }

        foreach (self::getAssessables() as $assessable) {
            $assessable->setDescription('');
            $planObject = (new PlanObject($assessable))->setDescription(null)->setIsDynamic(false);
            $data[]     = [new Participant(new User(), $planObject, $resultCacheMapper), ''];
        }

        foreach (self::getAssessables() as $assessable) {
            $assessable->setDescription('null');
            $planObject = (new PlanObject($assessable))->setDescription(null)->setIsDynamic(false);
            $data[]     = [new Participant(new User(), $planObject, $resultCacheMapper), 'null'];
        }

        foreach (self::getAssessables() as $assessable) {
            $assessable->setDescription(null);
            $planObject = (new PlanObject($assessable))->setDescription('null')->setIsDynamic(false);
            $data[]     = [new Participant(new User(), $planObject, $resultCacheMapper), 'null'];
        }

        foreach (self::getAssessables() as $assessable) {
            $assessable->setDescription('string2');
            $planObject = (new PlanObject($assessable))->setDescription('string1')->setIsDynamic(false);
            $data[]     = [new Participant(new User(), $planObject, $resultCacheMapper), 'string1'];
        }

        // Dynamic plan: always use assessment's description
        foreach (self::getAssessables() as $assessable) {
            $assessable->setDescription('string2');
            $planObject = (new PlanObject($assessable))->setDescription('string1')->setIsDynamic(true);
            $data[]     = [new Participant(new User(), $planObject, $resultCacheMapper), 'string2'];
        }

        return $data;
    }



    public static function providerDatesDomainObject()
    {
        $resultCacheMapper = ResultCache\Mapper::getInstance();

        $data = [];

        foreach (self::getDates() as $assessmentDate) {
            foreach (self::getDates() as $planDate) {
                foreach (self::getAssessables() as $assessable) {
                    if (!$assessmentDate) {
                        continue;
                    }
                    $assessable
                        ->setPublishAt(self::cloneDate($assessmentDate))
                        ->setOpenAt(self::cloneDate($assessmentDate))
                        ->setDueAt(self::cloneDate($assessmentDate))
                        ->setCloseAt(self::cloneDate($assessmentDate));

                    $planObject = (new PlanObject($assessable))
                        ->setPublishDate(self::cloneDate($planDate))
                        ->setOpenDate(self::cloneDate($planDate))
                        ->setDueDate(self::cloneDate($planDate))
                        ->setCloseDate(self::cloneDate($planDate))
                        ->setIsDynamic(false);

                    $data[] = [
                        new Participant(new User(), $planObject, $resultCacheMapper),
                        $planDate ?? $assessmentDate,
                        $planDate ?? $assessmentDate,
                        $planDate ?? $assessmentDate,
                        $planDate ?? $assessmentDate,
                    ];

                    // Dynamic plan: always use assessment's date
                    $dynamicPlanObject = (new PlanObject($assessable))
                        ->setPublishDate(self::cloneDate($planDate))
                        ->setOpenDate(self::cloneDate($planDate))
                        ->setDueDate(self::cloneDate($planDate))
                        ->setCloseDate(self::cloneDate($planDate))
                        ->setIsDynamic(true);
                    $data[] = [
                        new Participant(new User(), $dynamicPlanObject, $resultCacheMapper),
                        $assessmentDate,
                        $assessmentDate,
                        $assessmentDate,
                        $assessmentDate,
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
    private static function cloneDate(?Alaress_Date_Format $date): ?Alaress_Date_Format
    {
        return $date ? clone $date : null;
    }


    /**
     * @return IAssessable[]
     */
    private static function getAssessables(): array
    {
        return [
            new ProjectObject(),
            new DueWorkObject(),
            new QuizObject(),
            new TaskObject(),
        ];
    }


    /**
     * @return null[]|Alaress_Date_Format[]|Alaress_Date_Format[]
     */
    private static function getDates(): array
    {
        return [
            null,
            self::buildDate('2016-01-01 00:00:00'),
            self::buildDate('2016-01-01 12:00:00'),
            self::buildDate('2016-01-01 23:59:59'),
        ];
    }


    /**
     * @param string $dateString
     * @return Alaress_Date_Format
     */
    private static function buildDate(string $dateString): Alaress_Date_Format
    {
        return new Alaress_Date_Format($dateString, Alaress_Date_Format::DATABASE_FORMAT);
    }
}
