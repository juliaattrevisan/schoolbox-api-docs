<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Tour;

use Alaress\Web\Controller\Base;
use Schoolbox\Tour\Mapper as TourMapper;
use Schoolbox\Tour\Trigger\Mapper as TourTriggerMapper;
use Schoolbox\Tour\Trigger\Model as TourTrigger;

class Controller extends Base
{
    /** @var Mapper $tourMapper */
    private $tourMapper;

    /** @var TourTriggerMapper $tourTriggerMapper */
    private $tourTriggerMapper;

    /**
     * Constructor
     *
     * @param TourMapper $tourMapper
     */
    public function __construct(
        TourMapper $tourMapper,
        TourTriggerMapper $tourTrigerMapper
    )
    {
        $this->tourMapper        = $tourMapper;
        $this->tourTriggerMapper = $tourTrigerMapper;
    }

    /**
     * Retrieve all tours for the requested URL
     *
     * @api   GET /tours/url/{url}
     * @param string $url
     */
    public function getTours($url)
    {
        // Store the tours we are going to serve in an array

        $data = [];

        // Retrieve the URL we are loading tours for


        // Grab all tours for the logged in user/url

        $tours = $this->tourMapper->findByUserForUrl($this->getUser(), $url);

        // Iterate through them and add the required information into the array

        /** @var Model $tour */
        foreach ($tours as $tour) {
            // Grab all of the triggers for this tour

            $tourTriggers = $this->tourTriggerMapper->findForUrl($tour, $url);

            // Create an array to store the selectors/events

            $triggerArray = [];

            /** @var TourTrigger $tourTrigger */
            foreach ($tourTriggers as $tourTrigger) {

                // Add the selector/event to the return data

                $triggerArray[] = [
                    'selector' => $tourTrigger->getJavascriptSelector(),
                    'event'    => $tourTrigger->getJavascriptEvent(),
                ];
            }

            // Only add if one or more triggers was found

            if (count($triggerArray) > 0) {
                $data[] = [
                    'uuid'        => $tour->getUuid(),
                    'title'       => $tour->getTitle(),
                    'description' => $tour->getDescription(),
                    'triggers'    => $triggerArray,
                ];
            }
        }

        // Return results in JSON

        $this->getResponse()->status->set(200);
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }
}
