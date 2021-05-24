<?php

/**
 * Calendar_Event_Type_Submission
 *
 * Uses the Visitor pattern to render via an external class, finally decoupling
 * the data structure from rendering methodology in a highly granular environment
 * without needing to use singletons.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Type\Submission;

use Calendar\Event\Type\EventBase;
use Custom\Mggs\Calendar\Event\Type\Submission as Mggs;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Folder\Model as Folder;

class Event extends EventBase
{
    /**
     * Format for rendering events for the MGGS PBL calendar.
     */
    public const RENDER_FORMAT_JSON_PBL = 'pbl-javascript';


    /* Contextual: The owner of the submission box
      */
    protected $authorId   = -1;
    protected $authorName = '';

    /**
     * Category which the event is related to
     * @var Folder
     */
    protected $category = -1;

    /* TODO: Hardcoded?
     */
    protected $eventType = 'Due Work';

    /**
     * Is this an online assessment or not?
     *
     * @todo Debate whether this should be shoehorned into a submission event
     *
     * @var bool
     */
    protected $is_assessment = false;

    /**
     * The assessment that this event relates to.
     *
     * @var IAssessable
     */
    protected $assessment;

    /**
     * Get submission note
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getAssessment()->getBaseContext()->getFolder()->getTitle() . "\n" . $this->title;
    }

    /**
     * Event visibility; generates a CSS string
     *
     * @return string
     */
    public function getFilterClasses()
    {
        return 'personal';
    }

    /* Getters/Setters
     */

    /**
     * Get category codes
     *
     * @return array
     */
    public function getCategoryCodes()
    {
        return $this->getCategory()->getCodes();
    }

    /**
     * Get category string
     * @return string
     */
    public function getCategoryCodeString()
    {
        return $this->getCategory()->getCodeString();
    }

    /**
     * @deprecated
     * @param bool $is_assessment
     *
     * @return $this
     */
    public function setIsAssessment($is_assessment)
    {
//        $this->is_assessment = $is_assessment;
        return $this;
    }

    /**
     * @deprecated
     * @return bool
     */
    public function getIsAssessment()
    {
        return true;
//        return $this->is_assessment;
    }

    /**
     * @deprecated
     *
     * @param Folder $category
     */
    public function setCategory($category)
    {
//        $this->category = $category;
    }

    /**
     * @return Folder
     */
    public function getCategory()
    {
        return $this->getAssessment()->getBaseContext()->getFolder();
    }

    /**
     * @return IAssessable
     */
    public function getAssessment()
    {
        return $this->assessment;
    }

    /**
     * @param IAssessable $assessment
     * @return static
     */
    public function setAssessment(IAssessable $assessment)
    {
        $this->assessment = $assessment;
        return $this;
    }

    protected function getRenderClasses(): array
    {
        return [
            self::RENDER_FORMAT_JSON     => Renderer\Javascript::class,
            self::RENDER_FORMAT_VCAL     => Renderer\VObject::class,
            self::RENDER_FORMAT_CSV      => Renderer\Csv::class,
            self::RENDER_FORMAT_JSON_PBL => Mggs\Renderer\Javascript::class,
        ];
    }
}
