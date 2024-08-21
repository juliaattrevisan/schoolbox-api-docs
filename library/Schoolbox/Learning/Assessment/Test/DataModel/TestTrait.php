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
    protected $translator;

    /**
     * @var User
     */
    protected $teacher1;

    /**
     * @var User
     */
    protected $teacher2;


    /**
     * @return Assessment\Mapper
     */
    protected function getAssessmentMapper(): Assessment\Mapper
    {
        return Assessment\Mapper::getInstance();
    }

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->translator = new Translator(
            new MarkSchemeFactory(),
            $this->getAssessmentMapper(),
            10
        );
        $this->translator
            ->addSubTranslator(new Translator\Task())
            ->addSubTranslator(new Translator\DueWork())
            ->addSubTranslator(new Translator\Quiz())
            ->addSubTranslator(new Translator\Lti())
            ->addSubTranslator(new Translator\Project($this->translator))
        ;

        // Test users
        $this->teacher1 = (new User())
            ->setId(3)
            ->setExternalId(8)
            ->setTitle('Ms')
            ->setFirstname('Celia')
            ->setLastname('Glenn')
        ;

        $this->teacher2 = (new User())
            ->setId(151)
            ->setExternalId(165)
            ->setTitle('Mr')
            ->setFirstname('Zane')
            ->setLastname('Smith')
        ;
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
