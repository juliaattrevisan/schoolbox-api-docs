<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Fixtures\Venue;

use Alaress_Date_Format;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\Fixtures\Venue\Model as Venue;
use \Schoolbox\Fixtures\Venue\Mapper as VenueMapper;


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
     * @api GET /fixtures/venues
     * @since 165
     *
     * @param object $context
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getList($context = null)
    {

        /* ajax a list of venues
         */
        if ($this->getRequest()->isXhr()) {
            $query = $this->getRequest()->query->get('query');

            /** @var VenueMapper $mapper */
            $mapper    = $this->getMapper();
            $venues = [];
            try {
                $allVenues = $mapper->findByName($query);
                /** @var Venue $currentVenue */
                foreach ($allVenues as $currentVenue) {
                    $venues[] = array_combine(['id', 'name'], $currentVenue->findValueLabelPair());
                }
            } catch (MapperException $e) {
                // noop
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
     * @api GET /fixtures/venues/{venue.id}
     * @since 165
     *
     * @param Model $entity
     * @throws \Aura\Di\Exception\ServiceNotFound
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
     * @api GET /fixtures/venues/new
     * @api GET /fixtures/venues/{venue.id}/edit
     * @since 165
     *
     * @param Model $entity
     * @param object $context
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function getForm($entity, $context = null)
    {
        $data = [
            'venue'  => $entity,
            'action' => $entity->getId() ? 'Modify' : 'Create',
            'googleApiKey' => $this->getConfigLoader()->get('googleapps_api_key'),
        ];

        // compensate for failed API call
        if ($entity->getLatitude() && $entity->getLongitude()) {
            $data['location'] = [
                'lat' => $entity->getLatitude(),
                'lon' => $entity->getLongitude(),
            ];
        } else {
            $data['location'] = [
                'lat' => '-27.299860',
                'lon' => '133.622357',
            ];
            $data['zoom']     = 5;
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesVenue/create.twig', $data));
    }

    /**
     * Persist method for entity
     *
     * @api POST /fixtures/venues/
     * @api POST /fixtures/venues/{venue.id}
     * @since 165
     *
     * @param Model $entity
     * @throws \Aura\Router\Exception\RouteNotFound
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
     * @throws MapperException
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
