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
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Personalised\Participant;
use Schoolbox\Resource\Folder\Model as Folder;


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
     * @param Collection|Participant\Model[] $participation Student participation
     * @return Collection|array
     */
    public function buildFromAssessmentCollection(Collection $assessments, Collection $participation = null)
    {
        // At this time assume all $participation is for the same student
        return $assessments
            ->map(function (IAssessable $assessment) use ($participation) {
                return $this->buildFromAssessment($assessment, $participation);
            })
            ->concat();
    }

    /**
     * Builds a set of FullCalendar-compatible events from $assessable.
     *
     * @param IAssessable                    $assessable
     * @param Collection|Participant\Model[] $participation Student participation
     * @return Collection|array[]
     */
    public function buildFromAssessment(IAssessable $assessable, Collection $participation = null)
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
                'meta'       => [
                    'type'        => 'unit',
                    'description' => '',
                    'link'        => '/homepage/'.$context->getSpecification()->getFolder()->getId(),
                ],
            ]);


            // If this is a quiz or due work - it's a content area
            if ($assessable instanceof Quiz || $assessable instanceof DueWork) {
                // A background event for the bounds
                $result->add($this->buildAssessableEventBackground($assessable, $folder, 'contentArea'));

                // A foreground event for the actual quiz/duework
                $result->add($this->buildAssessableEvent($assessable, $folder, 'contentArea', $participation));

            } elseif ($assessable instanceof Project) {
                // If this is a project - get it and its constituents
                $result->add(
                    $this->buildAssessableEvent($assessable, $folder, 'project', $participation)
                    + ['editable' => false,]
                );

                foreach ($assessable->getAssessables() as $activity) {
                    // Background for the bounds
                    $result->add($this->buildAssessableEventBackground($activity, $folder, 'activity'));

                    // A foreground event for the actual activity
                    $result->add($this->buildAssessableEvent($activity, $folder, 'activity', $participation));
                }
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
            'start'      => $assessable->getPublishAt()->getDatabase(),
            'end'        => $assessable->getDueAt()->getDatabase(),
            'allDay'     => false,
            'resourceId' => $folder->getId().'-'.$resourceType,
            'rendering'  => 'background',
            'meta'       => [],
        ];
    }

    /**
     * Helper for building an event
     *
     * @param IAssessable $assessable
     * @param Folder      $folder
     * @param string      $resourceType
     * @param Collection  $participation
     * @return array
     */
    protected function buildAssessableEvent(
        IAssessable $assessable,
        Folder $folder,
        $resourceType,
        Collection $participation
    ) {
        // Find participation
        $start = $assessable->getPublishAt();
        $end   = $assessable->getDueAt();
        foreach ($participation as $participant) {
            /** @var Participant\Model $participant */
            if ($assessable->getId() == $participant->getAssessment()->getId()) {
                // Set times from participation
                if (!$participant->getOpenDate()->isNull()) {
                    $start = $participant->getOpenDate();
                }
                if (!$participant->getDueDate()->isNull()) {
                    $end = $participant->getDueDate();
                }
            }
        }

        return [
            'id'         => $resourceType.'-'.$assessable->getId(),
            'title'      => $assessable->getTitle(),
            'editable'   => true,
            'start'      => $start->getDatabase(),
            'end'        => $end->getDatabase(),
            'allDay'     => false,
            'resourceId' => $folder->getId().'-'.$resourceType,
            'constraint' => $resourceType.'-'.$assessable->getId().'-bounds',
            'meta'       => [
                'type'         => $assessable->getFamilyName(),
                'assessmentId' => $assessable->getId(),
                'description'  => \Scrub::html($assessable->getDescription()),
                'link'         => $this->generateLink($assessable),
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
