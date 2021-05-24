<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Tour;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper;
use Schoolbox\Tour\Mapper as TourMapper;
use Schoolbox\Tour\Trigger\Mapper as TourTriggerMapper;
use Schoolbox\Tour\Trigger\Model as TourTrigger;
use Schoolbox\User;

class Controller extends Base
{

    /** @var Mapper $tourMapper */
    private $tourMapper;

    /** @var Step\Mapper $tourStepMapper */
    private $tourStepMapper;

    /** @var Status\Mapper $tourStatusMapper */
    private $tourStatusMapper;


    /**
     * Constructor
     *
     * @param TourMapper    $tourMapper
     * @param Step\Mapper   $tourStepMapper
     * @param Status\Mapper $tourStatusMapper
     */
    public function __construct(
        TourMapper $tourMapper,
        Step\Mapper $tourStepMapper,
        Status\Mapper $tourStatusMapper
    ) {
        $this->tourMapper        = $tourMapper;
        $this->tourStepMapper    = $tourStepMapper;
        $this->tourStatusMapper  = $tourStatusMapper;
    }


    /**
     * Retrieve all tours for the requested URL
     *
     * @deprecated as of 18.2, tours for a URL are loaded directly into the page
     *
     * @api   GET /tours/url/{url}
     * @param string $url
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getTours($url)
    {
        $list = new Lists($this->tourMapper, $this->tourStepMapper);
        $data = $list->getTours($this->getUser(), $url);

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
        $user = $this->getUser();

        //Save new status
        $newTour = $this->tourMapper->createModel();
        $newTour->setUuid($post['tour_uuid']);

        $newTourStatus = $this->tourStatusMapper->createModel();
        $newTourStatus->setTour($newTour)
                      ->setUser($user)
                      ->setStatus($post['status']);
        try {
            //Save it real good
            $this->tourStatusMapper->save($newTourStatus);
            $this->getResponse()->status->set(204);
        } catch (Mapper\Exception $e) {
            //Attempted to save invalid data
            $this->getResponse()->content->set($this->buildJsonOutput('Invalid status data. Did not save status.'));
            $this->getResponse()->status->set(400);
        }
    }


    /**
     * @api POST /tourStatus/{uuid}
     * @param string $tour_uuid
     * @throws \Schoolbox\Mapper\Exception
     */
    public function tourStatus(string $tour_uuid)
    {
        $this->getResponse()->content->set($this->buildJsonOutput(null));

        /** @var User $user */
        $user = $this->getUser();

        //Find step that has status
        try {
            $status     = $this->tourStatusMapper->findOneByColumnList(["tour_uuid" => $tour_uuid, "user_id" => $user->getId()]);
            $serialized = $status->jsonSerialize();
            $this->getResponse()->content->set($this->buildJsonOutput($serialized));
        } catch (Mapper\Exception $e) {
            //No status for any of the steps - we are taking the tour for the first time;
            return;
        }
    }
}
