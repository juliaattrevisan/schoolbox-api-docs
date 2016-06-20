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
        $allSports = SportMapper::getInstance()->findAll([
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
            $sport = SportMapper::getInstance()->findById($id);

            $this->tpl('create', [
                'sport' => $sport,
                'action' => 'Modify'
            ]);
        }
        catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(2);
        }
    }

    /**
     * Save a Sport
     */
    public function postCreate()
    {
        $sport = new Model();
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
            $sport = Mapper::getInstance()->findById($this->getRequest()->post['id']);

            $this->saveSport($sport);
        }
        catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(2);
        }
    }

    /**
     * Delete a Division
     */
    public function postDelete()
    {
        try {
            /** @var Model $sport */
            $sport = Mapper::getInstance()->findById($this->getRequest()->post['id']);

            Mapper::getInstance()->delete($sport);
        }
        catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(2);
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

            Mapper::getInstance()->save($sport);

            $this->getResponse()->redirect->afterPost('/sportsfixtures/sport/list');
        }
        catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(2);
        }
    }
}
