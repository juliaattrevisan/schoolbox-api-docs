<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Tour;

use Schoolbox\User;

/**
 * Class Lists
 *
 * Generates a list of tours, this is used to help a page know if tours exist
 *
 * @package Schoolbox\Tour
 */
class Lists
{

    /**
     * @var Mapper
     */
    private $tourMapper;


    /**
     * @var Step\Mapper;
     */
    private $stepMapper;


    /**
     * Lists constructor.
     *
     * @param Mapper      $tourMapper
     * @param Step\Mapper $stepMapper
     */
    public function __construct(Mapper $tourMapper, Step\Mapper $stepMapper)
    {
        $this->tourMapper = $tourMapper;
        $this->stepMapper = $stepMapper;
    }


    /**
     * @param User $user
     * @param string $url
     * @return array
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getTours(User $user, $url): array
    {
        // Store the tours we are going to serve in an array

        $data = [];

        // Retrieve the URL we are loading tours for

        // Grab all tours for the logged in user/url

        $tours = $this->tourMapper->findByUserForUrl($user, $url);

        // Iterate through them and add the required information into the array

        /** @var Model $tour */
        foreach ($tours as $tour) {
            // Create an array to store the selectors/events
            $triggerArray = [];

            /** @var Trigger\Model $tourTrigger */
            foreach ($tour->getTriggerList() as $tourTrigger) {
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
                    'steps'       => $this->stepMapper->getStepArray($tour->getUuid()),
                ];
            }
        }

        return $data;
    }
}
