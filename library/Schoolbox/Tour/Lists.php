<?php
/**
 *
 * Generates a list of tours, this is used to help a page know if tours exist
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour;

use Schoolbox\User;

class Lists
{

    /**
     * @var Mapper
     */
    private $tourMapper;

    /**
     * Lists constructor.
     * @param Mapper $tourMapper
     */
    public function __construct(Mapper $tourMapper) {
        $this->tourMapper = $tourMapper;
    }

    /**
     * @param User $user
     * @param      $url
     * @return array
     */
    public function getTours(User $user, $url) : array {
        // Store the tours we are going to serve in an array

        $data = [];

        // Retrieve the URL we are loading tours for

        // Grab all tours for the logged in user/url

        $tours = $this->tourMapper->findByUserForUrl($user, $url);


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
            if (\count($triggerArray) > 0) {
                $data[] = [
                    'uuid'        => $tour->getUuid(),
                    'title'       => $tour->getTitle(),
                    'description' => $tour->getDescription(),
                    'triggers'    => $triggerArray,
                    'steps'       => $this->tourStepMapper->getStepArray($tour->getUuid()),
                ];
            }
        }
        return $data;
    }
}