<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Sport;

use Alaress\Web\Controller\Base;
use \Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;

class Controller extends Base
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
        $this->setSportMapper(SportMapper::getInstance());
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
            'template' => "@sportsfixturesSport/$template.twig",
            'data'     => $data,
        ];

        if ($this->getRequest()->isXhr()) {
            $content['format'] = 'nochrome';
        }

        $this->getResponse()->content->set($content);
    }

    /**
     * List of Sports
     */
    public function getList()
    {
        /* TODO: Not sure if we need this here
         * but I'll set it up anyway just in case
         */
        $allSports = $this->getSportMapper()->findAll([
            'order' => [
                'name' => 'asc'
            ]
        ]);

        $this->tpl('list', [
            'allSports' => $allSports
        ]);
    }

    /**
     * Create a Sport
     */
    public function getCreate()
    {
        $this->tpl('create', [
            'action' => 'Create'
        ]);
    }

    /**
     * Update a Sport
     *
     * @param int $id
     */
    public function getUpdate($id)
    {
        try {
            $sport = $this->getSportMapper()->findById($id);

            $this->tpl('create', [
                'sport' => $sport,
                'action' => 'Modify'
            ]);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Save a Sport
     */
    public function postCreate()
    {
        $sport = $this->getSportMapper()->createModel();
        $sport->setCreatedAt(\Alaress_Date_Format::now());

        $this->saveSport($sport);
    }

    /**
     * Save a Division
     */
    public function postUpdate()
    {
        try {
            /** @var Model $sport */
            $sport = $this->getSportMapper()->findById($this->getRequest()->post['id']);

            $this->saveSport($sport);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Delete a Division
     */
    public function postDelete()
    {
        try {
            /** @var Model $sport */
            $sport = $this->getSportMapper()->findById($this->getRequest()->post['id']);

            $this->getSportMapper()->delete($sport);
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
    }

    /**
     * Save Division data to db
     *
     * @param Model $sport
     */
    private function saveSport(Model $sport)
    {
        try {

            /** @var \Schoolbox\SportsFixtures\Sport\Model $sport */
            $sport->setName($this->getRequest()->post['name']);
            $sport->setUrl($this->getRequest()->post['url']);
            $sport->setUpdatedAt(\Alaress_Date_Format::now());

            $this->getSportMapper()->save($sport);

            $this->getResponse()->redirect->afterPost('/sportsfixtures/sport/list');
        }
        catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(2);
        }
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
