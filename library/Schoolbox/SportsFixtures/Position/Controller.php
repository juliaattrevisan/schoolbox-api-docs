<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Position;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use \Schoolbox\SportsFixtures\Position\Model as Position;
use \Schoolbox\SportsFixtures\Position\Mapper as PositionMapper;


class Controller extends Base
{

    /**
     * @var SportMapper
     */
    protected $sportMapper;

    /**
     * @var PositionMapper
     */
    protected $positionMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->setSportMapper(SportMapper::getInstance());
        $this->setPositionMapper(PositionMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * Just a raw template render
     *
     * @param string $template
     * @param array  $data
     */
    protected function tpl($template, $data = [])
    {
        $content = [
            'template' => "@sportsfixturesPosition/$template.twig",
            'data'     => $data,
        ];

        if ($this->getRequest()->isXhr()) {
            $content['format'] = 'nochrome';
        }

        $this->getResponse()->content->set($content);
    }

    /**
     * List of Positions and the Sport they are in
     *
     * @param int $sportId
     */
    public function getList($sportId)
    {
        try {
            $sport = $this->getSportMapper()->findById($sportId);

            $allPositions = $this->getPositionMapper()->findByColumn('sport_id', $sport->getId(), [
                'order' => [
                    'name' => 'asc'
                ]
            ]);

            $this->tpl('list', [
                'sport' => $sport,
                'allPositions' => $allPositions
            ]);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Create a Position in a particular Sport id
     *
     * @param int $sportId
     */
    public function getCreate($sportId)
    {
        try {
            $sport = $this->getSportMapper()->findById($sportId);

            $this->tpl('create', [
                'sport' => $sport,
                'action' => 'Create'
            ]);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Update a Position
     *
     * @param int $positionId
     */
    public function getUpdate($positionId)
    {
        try {
            $position = $this->getPositionMapper()->findById($positionId);
            $sport = $this->getSportMapper()->findById($position->getSport()->getId());

            $this->tpl('create', [
                'position' => $position,
                'sport' => $sport,
                'action' => 'Modify'
            ]);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Create a Position
     *
     * @param Position $position
     */
    public function postCreate(Position $position)
    {
        /* Get around aura's weird regexing
         */
        $this->postModify($position);
    }

    /**
     * Modify a Position
     *
     * @param Position $position
     */
    public function postModify(Position $position)
    {
        try {
            $sportId = $this->getRequest()->post['sportId'];
            $sport   = $this->getSportMapper()->findById($sportId);

            $this->savePosition($sport, $this->getRequest()->post['name'], $position);

            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesPosition.getList', [
                'sportId' => $sportId
            ]));

        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Delete a Position
     */
    public function postDelete()
    {
        try {
            /** @var Position $position */
            $position = $this->getPositionMapper()->findById($this->getRequest()->post['positionId']);

            $this->getPositionMapper()->delete($position);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Save Position data to db
     *
     * @param $sport
     * @param $name
     * @param Position $position
     */
    private function savePosition($sport, $name, Position $position)
    {
        $position->setName($name);
        $position->setSport($sport);
        $position->setUpdatedAt(Alaress_Date_Format::now());

        $this->getPositionMapper()->save($position);
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
     * @return PositionMapper
     */
    protected function getPositionMapper()
    {
        return $this->positionMapper;
    }

    /**
     * @param PositionMapper $positionMapper
     */
    protected function setPositionMapper($positionMapper)
    {
        $this->positionMapper = $positionMapper;
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