<?php
/**
 * Event source for the due work screen (/submission/dueWork)
 *
 * Differs from the base source in that its colors come from the color list.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class Calendar_Api_EventType_Submission_Schoolbox_ForDueWork extends Calendar_Api_EventType_Submission_Schoolbox_Default
{
    /**
     * @var Timetable_ColourManager
     */
    protected $colorManager;

    /**
     * {@inheritdoc}
     */
    public function __construct(\Schoolbox\User $user, $config)
    {
        parent::__construct($user, $config);
        $this->colorManager = new Timetable_ColourManager(
            Timetable_ColourMapper::getInstance()->findAll()
        );
    }

    /**
     * Gets the display color for an event.
     *
     * @param  \Schoolbox\Assessment\Activity $activity
     * @param  string                         $eventType
     * @return string
     */
    protected function getEventColor($activity, $eventType)
    {
        return $this->colorManager->getColour($activity->getCategory()->getId());
    }
}
