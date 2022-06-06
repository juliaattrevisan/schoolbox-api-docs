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
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Resource\Folder\Code\Model as FolderCode;
use Schoolbox\Resource\Folder\Model as Folder;

class Event extends EventBase
{

    /* Contextual: The owner of the submission box
      */
    protected $authorId   = -1;
    protected $authorName = '';

    /* TODO: Hardcoded?
     */
    protected $eventType = 'Due Work';

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
     * @return GenericCollection<FolderCode>|null
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
        ];
    }
}
