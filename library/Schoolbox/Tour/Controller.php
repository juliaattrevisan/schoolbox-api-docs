<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Tour;

use Alaress\Web\Controller\Base;
use Schoolbox\File\Exception;
use Schoolbox\Tour\Mapper as TourMapper;
use Schoolbox\Tour\Trigger\Mapper as TourTriggerMapper;
use Schoolbox\Tour\Trigger\Model as TourTrigger;
use Schoolbox\User;

class Controller extends Base
{

    /** @var Mapper $tourMapper */
    private $tourMapper;

    /** @var TourTriggerMapper $tourTriggerMapper */
    private $tourTriggerMapper;

    /** @var Step\Mapper $tourStepMapper */
    private $tourStepMapper;

    /** @var Status\Mapper $tourStatusMapper */
    private $tourStatusMapper;


    /**
     * Constructor
     *
     * @param TourMapper        $tourMapper
     * @param TourTriggerMapper $tourTriggerMapper
     * @param Step\Mapper       $tourStepMapper
     * @param Status\Mapper     $tourStatusMapper
     */
    public function __construct(
        TourMapper $tourMapper,
        TourTriggerMapper $tourTriggerMapper,
        Step\Mapper $tourStepMapper,
        Status\Mapper $tourStatusMapper
    ) {
        $this->tourMapper        = $tourMapper;
        $this->tourTriggerMapper = $tourTriggerMapper;
        $this->tourStepMapper    = $tourStepMapper;
        $this->tourStatusMapper  = $tourStatusMapper;
    }


    /**
     * Retrieve all tours for the requested URL
     *
     * @api   GET /tours/url/{url}
     * @param string $url
     * @throws \Schoolbox\Mapper\DefinitionException
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
                    'steps'       => $this->tourStepMapper->getStepArray($tour->getUuid()),
                ];
            }
        }

        // Return results in JSON
        $this->getResponse()->status->set(200);
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }


    /**
     * @api POST /url/{url}/{oldStep}
     * @param $oldStep
     * @param $url
     */
    public function saveStatus(?string $oldStep, string $url)
    {
        $this->getResponse()->content->set($this->buildJsonOutput(null));

        /** @var User $user */
        $user          = $this->getUser();

        //Delete previous step
        if ($oldStep != null) {
            try {
                $oldTourStatus = $this->tourStatusMapper->findByColumnList(["current_step" => $oldStep, "url" => $url, "user_id" => $user->getId()]);
                $this->tourStatusMapper->delete($oldTourStatus);
            } catch (\Schoolbox\Mapper\Exception $e) {
                //Could not find old step in db...
            }
        }

        //Save new status
        $newStep = $this->tourStepMapper->createModel();
        $newStep->setUuid($this->getRequest()->post['newStep']);

        $newTourStatus = $this->tourStatusMapper->createModel();
        $newTourStatus->setTourStep($newStep)
                      ->setUser($user)
                      ->setUrl($url)
                      ->setStatus($this->getRequest()->post['status']);

        try {
            $this->tourStatusMapper->save($newTourStatus);
        } catch (\Schoolbox\Mapper\Exception $e) {
            //Attempted to save invalid data
        }
    }


    /**
     * @api POST /tourStatus/{uuid}
     * @param $tour_uuid
     */
    public function tourStatus(string $tour_uuid)
    {
        $this->getResponse()->content->set($this->buildJsonOutput(null));

        //Get all steps for tour
        $steps = $this->tourStepMapper->findByColumnList(["tour_uuid" => $tour_uuid]);

        //Find step that has status
        try {
            $status     = $this->tourStatusMapper->findOneByColumnList(["current_step" => $steps->getPropertyValues("uuid")]);
            $serialized = $status->jsonSerialize();
            $this->getResponse()->content->set($this->buildJsonOutput($serialized));
        } catch (\Schoolbox\Mapper\Exception $e) {
            //No status for any of the steps - we are taking the tour for the first time;
            return;
        }
    }
}
