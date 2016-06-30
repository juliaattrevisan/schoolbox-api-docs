<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Venue;

use Alaress_Date_Format;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\SportsFixtures\Venue\Model as Venue;
use \Schoolbox\SportsFixtures\Venue\Mapper as VenueMapper;


class Controller extends AbstractController
{
    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(VenueMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * List of Venues
     *
     * @api GET /sportsfixtures/venues
     * @level 165
     *
     * @param object $context
     */
    public function getList($context = null)
    {

        /* ajax a list of venues
         */
        if ($this->getRequest()->isXhr()) {
            $query = $this->getRequest()->query->get('query');

            /** @var VenueMapper $mapper */
            $mapper    = $this->getMapper();
            $allVenues = $mapper->findByName($query);

            $venues = [];

            /** @var Venue $currentVenue */
            foreach ($allVenues as $currentVenue) {
                $venues[] = array_combine(['id', 'name'], $currentVenue->findValueLabelPair());
            }

            $this->getResponse()->content->set($this->buildJsonOutput($venues));
        }
        else {
            try {
                $allVenues = $this->mapper->findAll(
                    [
                        'order' => [
                            'name' => 'asc'
                        ]
                    ]
                );

                $this->getResponse()->content->set(
                    $this->buildHtmlOutput(
                        '@sportsfixturesVenue/list.twig',
                        [
                            'allVenues' => $allVenues
                        ]
                    )
                );
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_VENUE_NOT_FOUND);
            }
        }
    }

    /**
     * View a single venue
     *
     * @api GET /sportsfixures/venues/{venue.id}
     * @level 165
     *
     * @param Model $entity
     */
    public function getEntity($entity)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesVenue/entity.twig', [
            'venue' => $entity
        ]));
    }

    /**
     * Display form for create/update venue
     *
     * @api GET /sportsfixtures/venues/new
     * @api GET /sportsfixtures/venues/{venue.id}/edit
     * @level 165
     *
     * @param Model $entity
     * @param object $context
     */
    protected function getForm($entity, $context = null)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesVenue/create.twig', [
            'venue'  => $entity,
            'action' => $entity->getId() ? 'Modify' : 'Create'
        ]));
    }

    /**
     * Persist method for entity
     *
     * @api POST /sportsfixtures/venues/
     * @api POST /sportsfixtures/venues/{venue.id}
     * @level 165
     *
     * @param Model $entity
     */
    protected function persistEntity($entity)
    {
        try {
            $this->saveVenue(
                $entity,
                $this->getRequest()->post['name'],
                $this->getRequest()->post['latitude'],
                $this->getRequest()->post['longitude'],
                $this->getRequest()->post['address']
            );

            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesVenue.getList'));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_VENUE_NOT_FOUND);
        }
    }

    /**
     * Save Venue data to db
     *
     * @param Venue $venue
     * @param string $name
     * @param string $latitude
     * @param string $longitude
     * @param string $address
     */
    private function saveVenue(Venue $venue, $name, $latitude, $longitude, $address)
    {
        $venue->setName($name);
        $venue->setLatitude($latitude);
        $venue->setLongitude($longitude);
        $venue->setAddress($address);
        $venue->setUpdatedAt(Alaress_Date_Format::now());

        $this->mapper->save($venue);
    }

    /* Getters/Setters
     */

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     */
    protected function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }
}
