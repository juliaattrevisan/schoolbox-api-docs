<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Sport;

use Alaress\Web\Controller\Exception;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Fixtures\Sport\Model as Sport;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Division\Model as Division;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Position\Mapper as PositionMapper;
use Schoolbox\Fixtures\Position\Model as Position;

use \Schoolbox\Mapper as BaseMapper;

/**
 * @method Mapper getMapper()
 */
class Controller extends AbstractController
{
    /**
     * @var DivisionMapper
     */
    protected $divisionMapper;

    /** @var TeamMapper */
    protected $teamMapper;

    /** @var PositionMapper */
    protected $positionMapper;

    /** @var ErrorMapper */
    protected $errorMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct(SportMapper::getInstance());
        $this->setDivisionMapper(DivisionMapper::getInstance());
        $this->setTeamMapper(TeamMapper::getInstance());
        $this->setPositionMapper(PositionMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * List of Sports
     *
     * @param null $context
     */
    public function getList($context = null)
    {
        /* TODO: Not sure if we need this here
         * but I'll set it up anyway just in case
         */
        $allSports = $this->getMapper()->findAll([
            'order' => [
                'name' => 'asc'
            ]
        ]);

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesSport/list.twig', [
            'allSports' => $allSports
        ]));
    }

    /**
     * @param Sport $entity
     * @param object $context
     * @return void
     */
    protected function getForm($entity, $context = null)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesSport/create.twig', [
            'sport'  => $entity,
            'action' => $entity->getId() ? 'Modify' : 'Create'
        ]));
    }

    /**
     * Populate the Response object with a representation of the given entity:
     *  - in the format provided in the request Accepts header
     * If entity is deleted, respond with '210 Gone'
     *
     * @param Sport $entity
     * @throws Exception
     * @return void
     */
    public function getEntity($entity)
    {
        $divisions = $this->getDivisionMapper()->findByColumn('sport_id', $entity->getId());

        foreach ($divisions as $division) {
            /** @var Division $division */
            $division->setTeams($this->getTeamMapper()->findByColumn('division_id', $division->getId()));
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@sportsfixturesSport/entity.twig', [
            'sport'     => $entity,
            'divisions' => $divisions
        ]));
    }

    /**
     * Modify a Sport
     *
     * @param Sport $entity
     */
    public function persistEntity($entity)
    {
        try {
            $post = $this->getRequest()->post;

            $positions = $this->transposePositionPost($post['positions']);

            $this->saveSport($entity, $post['name'], $post['url'], $positions);

            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('sportsfixturesSport.getList'));
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
    }

    /**
     * Transposes the post-data of positions into a collection of actual objects
     *
     * @param array $posPost
     * @return Collection|Position[]
     * @throws MapperException
     */
    protected function transposePositionPost(array $posPost)
    {
        $c = $this->getPositionMapper()->createCollection();

        $pCount = count($posPost['id']);

        for ($i = 0; $i < $pCount; ++$i) {
            if (empty($posPost['name'][$i])) {
                continue;
            }

            $position = $this->getPositionMapper()->createModel($posPost['id'][$i]);
            $position->setName($posPost['name'][$i]);

            $c->add($position);
        }
        return $c;
    }

    /**
     * Save Sport data to db
     *
     * @param Sport $sport
     * @param string $name
     * @param string $url
     * @param Collection $positions
     */
    private function saveSport(Sport $sport, $name, $url, Collection $positions)
    {
        $urlFormatter = new \Url($url);
        $urlFormatter->normalise();

        $sport->setName(trim($name));
        $sport->setUrl($urlFormatter->getUrl());
        $sport->setUpdatedAt(\Alaress_Date_Format::now());
        $sport->setPositions($positions);

        $this->getMapper()->save($sport);
    }

    /* Getters/Setters
     */

    /**
     * @return DivisionMapper
     */
    protected function getDivisionMapper()
    {
        return $this->divisionMapper;
    }

    /**
     * @param DivisionMapper $divisionMapper
     */
    protected function setDivisionMapper($divisionMapper)
    {
        $this->divisionMapper = $divisionMapper;
    }


    /**
     * @return TeamMapper
     */
    public function getTeamMapper()
    {
        return $this->teamMapper;
    }


    /**
     * @param TeamMapper $teamMapper
     * @return static
     */
    public function setTeamMapper($teamMapper)
    {
        $this->teamMapper = $teamMapper;
        return $this;
    }

    /**
     * @return PositionMapper
     */
    public function getPositionMapper()
    {
        return $this->positionMapper;
    }

    /**
     * @param PositionMapper $positionMapper
     * @return static
     */
    public function setPositionMapper($positionMapper)
    {
        $this->positionMapper = $positionMapper;
        return $this;
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
