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
     * @api POST /url/save
     */
    public function saveStatus()
    {
        $this->getResponse()->content->set($this->buildJsonOutput(null));
        $post = $this->getRequest()->post;

        /** @var User $user */
        $user          = $this->getUser();

        //Delete previous status
        if ($post['oldStep'] != null) {
            try {
                $oldTourStatus = $this->tourStatusMapper->findByColumnList(["current_step" => $post['oldStep'], "url" => $post['url'], "user_id" => $user->getId()]);
                $this->tourStatusMapper->delete($oldTourStatus);
            } catch (\Schoolbox\Mapper\Exception $e) {
                //Could not find old step in db...
                $this->getResponse()->content->set($this->buildJsonOutput("Could not find previous status to delete. Will not save next status."));
                $this->getResponse()->status->set(404);
                return;
            }
        }

        //Save new status
        $newStep = $this->tourStepMapper->createModel();
        $newStep->setUuid($post['newStep']);

        $newTourStatus = $this->tourStatusMapper->createModel();
        $newTourStatus->setTourStep($newStep)
                      ->setUser($user)
                      ->setUrl($post['url'])
                      ->setStatus($post['status']);

        try {
            //Check if the corresponding step exists
            $this->tourStepMapper->findOneByColumn("uuid", $post['newStep']);
            $this->tourStatusMapper->save($newTourStatus);
            $this->getResponse()->status->set(204);
        } catch (\Schoolbox\Mapper\Exception $e) {
            //Attempted to save invalid data
            $this->getResponse()->content->set($this->buildJsonOutput("Invalid status data. Did not save status."));
            $this->getResponse()->status->set(400);
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
