<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Status;

use Alaress_Date_Format;
use Schoolbox\Application\WebRequest\Restful\AbstractController;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Fixtures\Result\Model as Result;
use Schoolbox\Fixtures\Status\Model as Status;
use Schoolbox\Fixtures\Status\Mapper as StatusMapper;

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
        parent::__construct(StatusMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * A list of Statuses that can be assigned to Results
     * of a Match
     *
     * @param Status $context
     */
    public function getList($context = null)
    {
        $allStatus = $this->mapper->findAll(
            [
                'order' => [
                    'created_at' => 'asc'
                ]
            ]
        );

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@sportsfixturesStatus/list.twig',
                [
                    'allStatus' => $allStatus
                ]
            )
        );
    }

    /**
     * View a single Status
     *
     * @param Model $entity
     */
    public function getEntity($entity)
    {
        /* noop */
    }

    /**
     * Display form for create/update Status
     *
     * @param Status $entity
     * @param Result $context
     */
    protected function getForm($entity, $context = null)
    {
        /* noop */
    }

    /**
     * Persist method for entity
     *
     * @param Model $entity
     */
    protected function persistEntity($entity)
    {
        $this->saveStatus($entity, $this->getRequest()->post['name']);

        if ($this->getRequest()->isXhr()) {
            $status = [
                'id' => $entity->getId(),
                'update' => $this->getRouter()->generate('sportsfixturesStatus.updateEntity', [
                    'entityId' => $entity->getId()
                ]),
                'delete' => $this->getRouter()->generate('sportsfixturesStatus.deleteEntity', [
                    'entityId' => $entity->getId()
                ])
            ];

            $this->getResponse()->content->set($this->buildJsonOutput($status));
        }
    }

    /**
     * Save Status data to db
     *
     * @param Status $entity
     * @param string $name
     *      */
    private function saveStatus($entity, $name)
    {
        $entity->setName($name);
        $entity->setUpdatedAt(Alaress_Date_Format::now());

        $this->getMapper()->save($entity);
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
