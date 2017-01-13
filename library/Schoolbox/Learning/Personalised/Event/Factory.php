<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Personalised\Event;

use Aura\Router\Router;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Personalised\Participant;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;


/**
 * Factory for building a set of FullCalendar-compatible events from an
 * IAssessable.
 */
class Factory
{
    /**
     * @var Router
     */
    protected $router;

    /**
     * Constructor
     *
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Builds a set of FullCalendar-compatible events from all $assessments.
     *
     * @param Collection|IAssessable[]       $assessments
     * @param User                           $student
     * @param Collection|Participant\Model[] $participation Student participation
     * @return Collection|array
     */
    public function buildFromAssessmentCollection(Collection $assessments, User $student, Collection $participation = null)
    {
        // At this time assume all $participation is for the same student
        return $assessments
            ->map(function (IAssessable $assessment) use ($student, $participation) {
                return $this->buildFromAssessment($assessment, $student, $participation);
            })
            ->concat();
    }

    /**
     * Builds a set of FullCalendar-compatible events from $assessable.
     *
     * @param IAssessable                    $assessable
     * @param User                           $student
     * @param Collection|Participant\Model[] $participation Student participation
     * @return Collection|array[]
     */
    public function buildFromAssessment(IAssessable $assessable, User $student, Collection $participation = null)
    {
        $result = new Collection();

        // Process unit implementations only
        if ($assessable->getContext() instanceof UnitImplementation) {
            /** @var UnitImplementation $context */
            $context = $assessable->getContext();

            /** @var Folder $folder */
            $folder = $context->getCourse()->getFolder();

            // The unit itself
            $result->add([
                'id'         => 'unit-'.$context->getId(),
                'title'      => $context->getTitle(),
                'editable'   => false,
                'start'      => $context->getOpenDate()->getDatabase(),
                'end'        => $context->getDueDate()->getDatabase(),
                'allDay'     => true,
                'resourceId' => $folder->getId().'-unit',
                'className'  => ['unit', 'readonly'],
                'meta'       => [
                    'type'        => 'unit',
                    'description' => '',
                    'link'        => '/homepage/'.$context->getSpecification()->getFolder()->getId(),
                ],
            ]);


            if ($assessable instanceof Project) {
                // If this is a project - get it and its constituents
                $projectRender = $this->buildAssessableEvent($assessable, $folder, 'project', $student, $participation);
                $projectRender['editable']    = false;
                $projectRender['className'][] = 'readonly';
                $result->add($projectRender);

                foreach ($assessable->getAssessables() as $activity) {
                    // Background for the bounds
                    $result->add($this->buildAssessableEventBackground($activity, $folder, 'activity'));

                    // A foreground event for the actual activity
                    $result->add($this->buildAssessableEvent($activity, $folder, 'activity', $student, $participation));
                }

            } else {
                // It's a content area

                // A background event for the bounds
                $result->add($this->buildAssessableEventBackground($assessable, $folder, 'contentArea'));

                // A foreground event for the actual quiz/duework
                $result->add($this->buildAssessableEvent($assessable, $folder, 'contentArea', $student, $participation));
            }
        }

        return $result;
    }

    /**
     * Helper for building an event background
     *
     * @param IAssessable $assessable
     * @param Folder      $folder
     * @param string      $resourceType
     * @return array
     */
    protected function buildAssessableEventBackground(IAssessable $assessable, Folder $folder, $resourceType)
    {
        return [
            'id'         => $resourceType.'-'.$assessable->getId().'-bounds',
            'editable'   => false,
            'start'      => $assessable->getOpenAt()->getDatabase(),
            'end'        => $assessable->getDueAt()->getDatabase(),
            'allDay'     => true,
            'resourceId' => $folder->getId().'-'.$resourceType,
            'rendering'  => 'background',
            'className'  => [$resourceType],
            'meta'       => [],
        ];
    }

    /**
     * Helper for building an event
     *
     * @param IAssessable $assessable
     * @param Folder      $folder
     * @param string      $resourceType
     * @param User        $student
     * @param Collection  $participation
     * @return array
     */
    protected function buildAssessableEvent(
        IAssessable $assessable,
        Folder $folder,
        $resourceType,
        User $student,
        Collection $participation
    ) {
        // Find participation
        $assessmentStart = $studentStart = $assessable->getOpenAt();
        $assessmentEnd   = $studentEnd   = $assessable->getDueAt();
        foreach ($participation as $participant) {
            /** @var Participant\Model $participant */
            if ($assessable->getId() == $participant->getAssessment()->getId()) {
                // Set times from participation
                if (!$participant->getOpenDate()->isNull()) {
                    $studentStart = $participant->getOpenDate();
                }
                if (!$participant->getDueDate()->isNull()) {
                    $studentEnd = $participant->getDueDate();
                }
                break;
            }
        }

        // Has this student mastered the assessment?
        $className = [$resourceType];
        if ($assessable->isParticipant($student)) {
            $participantObject = $assessable->getParticipant($student);
            $feedback          = $participantObject->findMostRecentPublishedFeedback();

            if ($feedback instanceof IFeedback) {
                $mark = $feedback->getNormalisedMark();
                if (isset($mark)) {
                    // > mastery threshold (hardcode to 70 for now)
                    $className[] = ($mark >= 70 ? 'mastered' : 'fail');
                }

            } else {
                // No feedback yet. Is there a response?
                if (!$participantObject->isMarked() && $participantObject->isResponded()) {
                    $className[] = 'notmarked';
                }

            }
        }

        return [
            'id'         => $resourceType.'-'.$assessable->getId(),
            'title'      => $assessable->getTitle(),
            'editable'   => true,
            'start'      => $studentStart->getDatabase(),
            'end'        => $studentEnd->getDatabase(),
            'allDay'     => true,
            'resourceId' => $folder->getId().'-'.$resourceType,
            'constraint' => $resourceType.'-'.$assessable->getId().'-bounds',
            'className'  => $className,
            'meta'       => [
                'type'            => $assessable->getFamilyName(),
                'assessmentId'    => $assessable->getId(),
                'description'     => \Scrub::html($assessable->getDescription()),
                'link'            => $this->generateLink($assessable),
                'assessmentStart' => $assessmentStart->getDatabase(),
                'assessmentEnd'   => $assessmentEnd->getDatabase(),
            ],
        ];
    }

    /**
     * Helper for generating a link to an assessable.
     *
     * @param IAssessable $assessable
     * @return string
     */
    protected function generateLink(IAssessable $assessable)
    {
        return $this->router->generate('learningAssessment.getSummary', ['assessmentId' => $assessable->getId()]);
    }
}
