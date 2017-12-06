<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test\DataModel;

use Schoolbox\Assessment\Component\SubmissionBox as DueWorkModel;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Learning\Assessment\DataModel\Translator;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
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
     * Set up
     */
    protected function setUp()
    {
        $this->translator = new Translator(new MarkSchemeFactory());
        $this->translator
            ->addSubTranslator(new Translator\Task())
            ->addSubTranslator(new Translator\DueWork())
            ->addSubTranslator(new Translator\Quiz())
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
    protected function buildMarkType($markTypeId = DueWorkModel\MarkType::TYPE_NONE): DueWorkModel\MarkType
    {
        return (new DueWorkModel\MarkType())->setId($markTypeId);
    }
}
