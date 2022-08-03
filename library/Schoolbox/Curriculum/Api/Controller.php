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
use Schoolbox\Mapper\Exception as MapperException;

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

    /**
     * Endpoint for fetching a curriculum node.
     *
     * @api   GET /api/curriculum/{id}
     * @since 22.1
     *
     * @param Node\Model $node
     * @return void
     */
    public function getNode(Node\Model $node): void
    {
        $this->respondWithNode($node);
    }

    /**
     * Endpoint for searching for curriculum nodes.
     *
     * @api   GET /api/curriculum/
     * @since 22.1
     *
     * @return void
     */
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

    /**
     * Endpoint for creating a curriculum node.
     *
     * @api   POST /api/curriculum/{id}
     * @since 22.1
     *
     * @return void
     */
    public function postNode(): void
    {
        $this->respondWithNode($this->persistNode(null));
    }

    /**
     * Endpoint for updating a curriculum node.
     *
     * @api   PUT /api/curriculum/{id}
     * @since 22.1
     *
     * @param Node\Model $node
     * @return void
     */
    public function putNode(Node\Model $node): void
    {
        $this->respondWithNode($this->persistNode((new Node\Model())->setId($node->getId())));
    }

    /**
     * Endpoint for partially updating a curriculum node.
     *
     * @api   PATCH /api/curriculum/{id}
     * @since 22.1
     *
     * @param Node\Model $node
     * @return void
     */
    public function patchNode(Node\Model $node): void
    {
        $this->respondWithNode($this->persistNode($node));
    }

    /**
     * Endpoint for deleting a curriculum node.
     *
     * @api   DELETE /api/curriculum/{id}
     * @since 22.1
     *
     * @param Node\Model $node
     * @return void
     */
    public function deleteNode(Node\Model $node): void
    {
        $this->nodeMapper->delete($node);
        $this->response->status->setCode(204);
    }

    /**
     * Persist and return a node, based on the contents of the request body and
     * the previous state of the node.
     *
     * @param Node\Model|null $previous If null, create a new node: otherwise,
     *                                  update the fields in `$previous`.
     * @return Node\Model
     * @throws MapperException if somehow the model couldn't be saved
     */
    private function persistNode(?Node\Model $previous): Node\Model
    {
        $data          = json_decode(json_encode($this->request->content->get()), true);
        $translator    = new ArrayToModel();
        $persistedNode = $translator($data, $previous);
        $this->nodeMapper->save($persistedNode);
        return $persistedNode;
    }

    /**
     * Output a node in the response.
     *
     * @param Node\Model $node
     * @return void
     */
    private function respondWithNode(Node\Model $node): void
    {
        $translator = new ModelToArray();
        $this->response->content->set(
            $this->buildJsonOutput($translator($node))
        );
    }
}
