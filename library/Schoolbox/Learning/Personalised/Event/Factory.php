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
     * Builds a set of FullCalendar-compatible events from $assessable.
     *
     * @param IAssessable $assessable
     * @return Collection|array[]
     */
    public function buildFromAssessment(IAssessable $assessable)
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
                'resourceId' => $folder->getId().'-units',
                'meta'       => [
                    'type'        => 'unit',
                    'description' => '',
                    'link'        => '/homepage/'.$context->getSpecification()->getFolder()->getId(),
                ],
            ]);


            // If this is a quiz or due work - it's a content area
            if ($assessable instanceof Quiz || $assessable instanceof DueWork) {
                // A background event for the bounds
                $result->add([
                    'id'         => 'contentArea-'.$assessable->getId().'-bounds',
                    'editable'   => false,
                    'start'      => $assessable->getPublishAt()->getDatabase(),
                    'end'        => $assessable->getDueAt()->getDatabase(),
                    'allDay'     => false,
                    'resourceId' => $folder->getId().'-contentAreas',
                    'rendering'  => 'background',
                    'meta'       => [],
                ]);

                // A foreground event for the actual quiz/duework
                $result->add([
                    'id'         => 'contentArea-'.$assessable->getId(),
                    'title'      => $assessable->getTitle(),
                    'editable'   => true,
                    'start'      => $assessable->getPublishAt()->getDatabase(),
                    'end'        => $assessable->getDueAt()->getDatabase(),
                    'allDay'     => false,
                    'resourceId' => $folder->getId().'-contentAreas',
                    'constraint' => 'contentArea-'.$assessable->getId().'-bounds',
                    'meta'       => [
                        'type'         => $assessable->getFamilyName(),
                        'assessmentId' => $assessable->getId(),
                        'description'  => \Scrub::html($assessable->getDescription()),
                        'link'         => $this->generateLink($assessable),
                    ],
                ]);

            } elseif ($assessable instanceof Project) {
                // If this is a project - get it and its constituents
                $result->add([
                    'id'         => 'project-'.$assessable->getId(),
                    'title'      => $assessable->getTitle(),
                    'editable'   => false,
                    'start'      => $assessable->getPublishAt()->getDatabase(),
                    'end'        => $assessable->getDueAt()->getDatabase(),
                    'allDay'     => false,
                    'resourceId' => $folder->getId().'-projects',
                    'meta'       => [
                        'type'         => $assessable->getFamilyName(),
                        'assessmentId' => $assessable->getId(),
                        'description'  => \Scrub::html($assessable->getDescription()),
                        'link'         => $this->generateLink($assessable),
                    ],
                ]);

                foreach ($assessable->getAssessables() as $activity) {
                    // Background for the bounds
                    $result->add([
                        'id'         => 'activity-'.$activity->getId().'-bounds',
                        'editable'   => false,
                        'start'      => $activity->getPublishAt()->getDatabase(),
                        'end'        => $activity->getDueAt()->getDatabase(),
                        'allDay'     => false,
                        'resourceId' => $folder->getId().'-activities',
                        'rendering'  => 'background',
                        'meta'       => [],
                    ]);

                    // A foreground event for the actual activity
                    $result->add([
                        'id'         => 'activity-'.$activity->getId(),
                        'title'      => $activity->getTitle(),
                        'editable'   => true,
                        'start'      => $activity->getPublishAt()->getDatabase(),
                        'end'        => $activity->getDueAt()->getDatabase(),
                        'allDay'     => false,
                        'resourceId' => $folder->getId().'-activities',
                        'constraint' => 'activity-'.$activity->getId().'-bounds',
                        'meta'       => [
                            'type'         => $activity->getFamilyName(),
                            'assessmentId' => $activity->getId(),
                            'description'  => \Scrub::html($activity->getDescription()),
                            'link'         => $this->generateLink($activity),
                        ],
                    ]);
                }
            }
        }

        return $result;
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
