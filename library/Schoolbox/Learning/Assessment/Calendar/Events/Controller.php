<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Calendar\Events;

use Alaress\Web\Controller\Base;
use Calendar\Event\Collection\Renderer;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;

/**
 * Controller for retrieving calendar events for the assessment calendar.
 */
class Controller extends Base
{
    /**
     * @var AssessmentDataSource
     */
    private $assessmentDataSource;

    /**
     * Constructor
     *
     * @param AssessmentDataSource $assessmentDataSource
     */
    public function __construct(AssessmentDataSource $assessmentDataSource)
    {
        $this->assessmentDataSource = $assessmentDataSource;
    }


    /**
     * Returns the events for the assessment calendar, as a JSON feed.
     *
     * @api   GET /learning/assessment/calendar/events
     * @since 19.1
     */
    public function getEvents(): void
    {
        // Must have:
        // start and end dates, not greater than 2 months between?
        $calendar = new Renderer(
            new Api($this->assessmentDataSource),
            $this->request->query->getInt('start'),
            $this->request->query->getInt('end'),
            $this->getUser(),
            $this->getDi(),
            $this->getUser()
        );

        $output = array_map('json_decode', $calendar->renderAll('javascript'));
        $this->response->content->set($this->buildJsonOutput($output));
    }
}
