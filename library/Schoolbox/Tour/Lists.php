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
     * Lists constructor.
     *
     * @param Mapper      $tourMapper
     * @param Step\Mapper $stepMapper
     */
    public function __construct(private Mapper $tourMapper, private Step\Mapper $stepMapper)
    {
    }


    /**
     * @param User $user
     * @param string $url
     * @param string|null $version
     * @return array
     */
    public function getTours(User $user, $url, ?string $version): array
    {
        // Store the tours we are going to serve in an array

        $data = [];

        // Retrieve the URL we are loading tours for

        // Grab all tours for the logged in user/url

        if (null === $version) {
            $tours = $this->tourMapper->findByUserForUrl(user: $user, url: $url, version: $version);
        } else {
            /* Persistent version-specific tour in the footer
             */
            $tours = $this->tourMapper->findByUserForUrl(user: $user, url: $url, version: $version, repeat: true);
        }


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
