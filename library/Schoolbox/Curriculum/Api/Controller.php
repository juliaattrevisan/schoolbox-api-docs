<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Api;

use Alaress\Web\Controller\Base;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Curriculum\Api\Translator\ArrayToModel;
use Schoolbox\Curriculum\Api\Translator\ModelToArray;
use Schoolbox\Curriculum\Node;
use Schoolbox\Curriculum\LearningArea;
use Schoolbox\Curriculum\LearningLevel;

/**
 * Controller for the curriculum API.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param Node\Mapper     $nodeMapper      Curriculum node mapper
     * @param ArticleResolver $articleResolver Article resolver: required in search for some reason?
     */
    public function __construct(
        private Node\Mapper $nodeMapper,
        private ArticleResolver $articleResolver
    ) {
    }

    public function getNode(Node\Model $node): void
    {
        $this->respondWithNode($node);
    }

    public function searchNode(): void
    {
        $results = $this->nodeMapper->search(
            $this->articleResolver,
            $this->getUser(),
            trim($this->request->query->get('query') ?? ''),
        );
        $this->response->content->set(
            $this->buildJsonOutput($results->map(new ModelToArray()))
        );
    }

    public function postNode(): void
    {
        $data       = json_decode(json_encode($this->request->content->get()), true);
        $translator = new ArrayToModel();
        $newNode    = $translator($data);
        $this->nodeMapper->save($newNode);
        $this->respondWithNode($newNode);
    }

    public function putNode(Node\Model $node): void
    {
        $data        = json_decode(json_encode($this->request->content->get()), true);
        $translator  = new ArrayToModel();
        $updatedNode = $translator($data, (new Node\Model())->setId($node->getId()));
        $this->nodeMapper->save($updatedNode);
        $this->respondWithNode($updatedNode);
    }

    public function patchNode(Node\Model $node): void
    {
        $data        = json_decode(json_encode($this->request->content->get()), true);
        $translator  = new ArrayToModel();
        $updatedNode = $translator($data, $node);
        $this->nodeMapper->save($updatedNode);
        $this->respondWithNode($updatedNode);
    }

    public function deleteNode(Node\Model $node): void
    {
        $this->nodeMapper->delete($node);
        $this->response->status->setCode(204);
    }

    private function respondWithNode(Node\Model $node): void
    {
        $translator = new ModelToArray();
        $this->response->content->set(
            $this->buildJsonOutput($translator($node))
        );
    }
}
