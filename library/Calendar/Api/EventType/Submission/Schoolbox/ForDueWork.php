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

use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Resource\Folder\Model as Folder;

class Calendar_Api_EventType_Submission_Schoolbox_ForDueWork extends Calendar_Api_EventType_Submission_Schoolbox_Default
{
    /**
     * @var Timetable_ColourManager
     */
    private $colorManager;

    /**
     * {@inheritdoc}
     */
    public function __construct(\Schoolbox\User $user, $config, AssessmentDataSource $assessmentDataSource)
    {
        parent::__construct($user, $config, $assessmentDataSource);
        $this->colorManager = new Timetable_ColourManager(
            Timetable_ColourMapper::getInstance()->findAll()
        );
    }

    /**
     * Gets the display color for an event.
     *
     * @param  Folder $folder
     * @param  string $eventType
     * @return string
     */
    protected function getEventColor(Folder $folder, $eventType): string
    {
        return $this->colorManager->getColour($folder->getId());
    }
}
