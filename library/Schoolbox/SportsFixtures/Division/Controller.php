<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Division;

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
            'template' => "@sportsfixturesDivision/$template.twig",
            'data'     => $data,
        ];

        if ($this->getRequest()->isXhr()) {
            $content['format'] = 'nochrome';
        }

        $this->getResponse()->content->set($content);
    }

    /**
     * Create a Division
     */
    public function getCreate()
    {
        $this->tpl('create', [
            'action' => 'Create'
        ]);
    }

    /**
     * Update a Division
     *
     * @param int $id
     */
    public function getUpdate($id)
    {
        try {
            $division = Mapper::getInstance()->findById($id);

            $this->tpl('create', [
                'division' => $division,
                'action' => 'Modify'
            ]);
        }
        catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(2);
        }
    }

    /**
     * Save a Division
     */
    public function postCreate()
    {
        $division = new Model();
        $division->setCreatedAt(\Alaress_Date_Format::now());

        $this->saveDivision($division);
    }

    /**
     * Save a Division
     */
    public function postUpdate()
    {
        /** @var Model $division */
        $division = Mapper::getInstance()->findById($this->getRequest()->post['id']);

        $this->saveDivision($division);
    }

    /**
     * Save Division data to db
     *
     * @param Model $division
     */
    private function saveDivision(Model $division)
    {
        try {

            /** @var \Schoolbox\SportsFixtures\Sport\Model $sport */
            $sportMapper = SportMapper::getInstance();
            $sport       = $sportMapper->findById($this->getRequest()->post['sportId']);

            $division->setName($this->getRequest()->post['name']);
            $division->setUrl($this->getRequest()->post['url']);
            $division->setSport($sport);
            $division->setUpdatedAt(\Alaress_Date_Format::now());

            Mapper::getInstance()->save($division);

            $this->getResponse()->redirect->afterPost('/sportsfixtures/division/create');
        }
        catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(2);
        }
    }

    /**
     * Public test
     */
    public function test()
    {
        $this->tpl('test', []);
    }
}
