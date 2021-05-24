<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Position;

use Alaress_Date_Format;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Fixtures\Sport\Model as Sport;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Position\Model as Position;
use Schoolbox\Fixtures\Position\Mapper as PositionMapper;

class Controller extends AbstractController
{

    /**
     * @var SportMapper
     */
    protected $sportMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(PositionMapper::getInstance());
        $this->setSportMapper(SportMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * List of Positions and the Sport they are in
     *
     * @param Sport $context
     */
    public function getList($context = null)
    {
        try {
            $allPositions = $this->mapper->findByColumn(
                'sport_id',
                $context->getId(),
                [
                    'order' => [
                        'name' => 'asc'
                    ]
                ]
            );

            $this->getResponse()->content->set(
                $this->buildHtmlOutput(
                    '@sportsfixturesPosition/list.twig',
                    [
                        'sport'        => $context,
                        'allPositions' => $allPositions
                    ]
                )
            );
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }
    }

    /**
     * View a single position
     *
     * @param Model $entity
     */
    public function getEntity($entity)
    {
        try {
            $sport = $this->getSportMapper()->findById($entity->getSport()->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesPosition/entity.twig', [
            'sport'    => $sport,
            'position' => $entity
        ]));
    }

    /**
     * Display form for create/update position
     *
     * @param Model $entity
     * @param Sport $context
     */
    protected function getForm($entity, $context = null)
    {
        if (empty($context)) {
            try {
                $context = $this->getSportMapper()->findById($entity->getSport()->getId());
            } catch (MapperException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
            }
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesPosition/create.twig', [
            'position' => $entity,
            'sport'    => $context,
            'action'   => $entity->getId() ? 'Modify' : 'Create'
        ]));
    }

    /**
     * Persist method for entity
     *
     * @param Model $entity
     */
    protected function persistEntity($entity)
    {
        try {
            $sportId = $this->getRequest()->post['sportId'];
            $sport   = $this->getSportMapper()->findById($sportId);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_SPORTSFIXTURES_SPORT_NOT_FOUND);
        }

        $this->savePosition($sport, $this->getRequest()->post['name'], $entity);

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesPosition.getList', [
            'contextId' => $sportId
        ]));
    }

    /**
     * Save Position data to db
     *
     * @param Sport $sport
     * @param string $name
     * @param Position $position
     */
    private function savePosition($sport, $name, Position $position)
    {
        $position->setName($name);
        $position->setSport($sport);
        $position->setUpdatedAt(Alaress_Date_Format::now());

        $this->mapper->save($position);
    }

    /* Getters/Setters
     */

    /**
     * @return SportMapper
     */
    protected function getSportMapper()
    {
        return $this->sportMapper;
    }

    /**
     * @param SportMapper $sportMapper
     */
    protected function setSportMapper($sportMapper)
    {
        $this->sportMapper = $sportMapper;
    }

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
