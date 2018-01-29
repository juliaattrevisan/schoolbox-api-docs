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

use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Resource\Folder;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;

class Calendar_Api_EventType_Submission_Schoolbox_ForDueWork extends Calendar_Api_EventType_Submission_Schoolbox_Default
{
    /**
     * @var Timetable_ColourManager
     */
    private $colourManager;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        int $connectorId,
        User $user,
        Assessment\MapperProxy $assessmentDataSource,
        Participant\DataSource $participantDataSource,
        Visibility             $visibility,
        Timetable_ColourManager $colourManager
    ) {
        parent::__construct($connectorId, $user, $assessmentDataSource, $participantDataSource, $visibility);
        $this->colourManager = $colourManager;
    }

    /**
     * Gets the display color for an event.
     *
     * @param  Folder\Model $folder
     * @param  string $eventType
     * @return string
     */
    protected function getEventColor(Folder\Model $folder, $eventType): string
    {
        return $this->colourManager->getColour($folder->getId());
    }
}
