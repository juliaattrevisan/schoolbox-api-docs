<?php
/**
 * Calendar_Event_Type_Submission
 *
 * Uses the Visitor pattern to render via an external class, finally decoupling
 * the data structure from rendering methodology in a highly granular environment
 * without needing to use singletons.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Calendar\Event\Type\Submission;
use Calendar\Event\Type\EventBase;
use Calendar\Event\Type\EventInterface;
use Schoolbox\Resource\Folder\Model as Folder;

class Event extends EventBase implements EventInterface {

    /**
     * Renderer classes
     */
    protected $renderClasses = array(   'javascript'    => Renderer\Javascript::class,
                                        'html'          => Renderer\Html::class,
                                        'vcal'          => Renderer\VObject::class,
                                        'csv'           => Renderer\Csv::class,
    );


    /* Contextual: The owner of the submission box
      */
    protected $authorId = -1;
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
     * Get submission note
	 *
	 * @return string
	 */
    public function getTitle() {

        /* Optional categoryCodes suffix
         */
        if (count($this->getCategoryCodes())) {
            $extended = ' (' . $this->getCategoryCodeString() . ')';
        } else {
            $extended = '';
        }

        return $this->getCategory()->getName() . $extended . "\n" . $this->title;
    }

	/**
	 * Event visibility; generates a CSS string
	 *
	 * @return string
	 */
	public function getFilterClasses() {
		return 'personal';
	}

    /* Getters/Setters
     */

    /**
     * Get category codes
     *
     * @return array
     */
    public function getCategoryCodes() {
        return $this->getCategory()->getCodes();
    }

    /**
     * Get category string
     * @return string
     */
    public function getCategoryCodeString() {
        return $this->getCategory()->getCodeString();
    }

    /**
     * @param  bool $is_assessment
     *
     * @return $this
     */
    public function setIsAssessment($is_assessment) {
        $this->is_assessment = $is_assessment;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsAssessment() {
        return $this->is_assessment;
    }

    /**
     * @param Folder $category
     */
    public function setCategory($category)
    {
        $this->category = $category;
    }

    /**
     * @return Folder
     */
    public function getCategory()
    {
        return $this->category;
    }



}
