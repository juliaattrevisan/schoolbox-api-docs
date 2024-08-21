<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel;

use Schoolbox\Assessment\Component\SubmissionBox as DueWorkModel;
use Schoolbox\Learning\Assessment\DataModel\Translator;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\User;

/**
 * Common code for translator tests.
 */
trait TestTrait
{
    /**
     * @var Translator
     */
    protected static $translator;

    /**
     * @var User
     */
    protected static $teacher1;

    /**
     * @var User
     */
    protected static $teacher2;


    /**
     * @return Assessment\Mapper
     */
    protected static function getAssessmentMapper(): Assessment\Mapper
    {
        return Assessment\Mapper::getInstance();
    }

    /**
     * Clear translator and teacher users between tests: otherwise, translations
     * stored within the translator will contaminate future tests.
     *
     * @return void
     */
    protected function setUp(): void
    {
        self::$translator = null;
        self::$teacher1 = null;
        self::$teacher2 = null;
    }

    protected static function getTranslator(): Translator
    {
        if (!isset(self::$translator)) {
            self::$translator = new Translator(
                new MarkSchemeFactory(),
                self::getAssessmentMapper(),
                10
            );
            self::$translator
                ->addSubTranslator(new Translator\Task())
                ->addSubTranslator(new Translator\DueWork())
                ->addSubTranslator(new Translator\Quiz())
                ->addSubTranslator(new Translator\Lti())
                ->addSubTranslator(new Translator\Project(self::$translator))
            ;
        }
        return self::$translator;
    }

    /**
     * @return User
     */
    protected static function getTeacher1(): User
    {
        // Test users
        self::$teacher1 ??= (new User())
            ->setId(3)
            ->setExternalId(8)
            ->setTitle('Ms')
            ->setFirstname('Celia')
            ->setLastname('Glenn')
        ;
        return self::$teacher1;
    }

    /**
     * @return User
     */
    protected static function getTeacher2(): User
    {
        // Test users
        self::$teacher2 ??= (new User())
            ->setId(151)
            ->setExternalId(165)
            ->setTitle('Mr')
            ->setFirstname('Zane')
            ->setLastname('Smith')
        ;
        return self::$teacher2;
    }

    /**
     * Helper function for creating a mark type given a mark type ID.
     *
     * @param int $markTypeId
     * @return DueWorkModel\MarkType
     */
    protected static function buildMarkType($markTypeId = DueWorkModel\MarkType::TYPE_NONE): DueWorkModel\MarkType
    {
        return (new DueWorkModel\MarkType())->setId($markTypeId);
    }
}
