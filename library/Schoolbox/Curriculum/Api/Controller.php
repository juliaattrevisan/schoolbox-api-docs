<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Api;

use Alaress\Web\Controller\Base;
use Schoolbox\Curriculum\Api\Translator\ArrayToModel;
use Schoolbox\Curriculum\Api\Translator\ModelToArray;
use Schoolbox\Curriculum\Node;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Controller for the curriculum API.
 */
class Controller extends Base
{
    private const MAX_ANCESTORS   = 50;
    private const MAX_DESCENDANTS = 50;
    private const MIN_LIMIT       = 1;
    private const DEFAULT_LIMIT   = 10;
    private const MAX_LIMIT       = 1000;

    /**
     * Constructor
     *
     * @param Node\Mapper   $nodeMapper Curriculum node mapper
     * @param Usage\Counter $counter    Counter for node usages
     */
    public function __construct(
        private Node\Mapper $nodeMapper,
        private Usage\Counter $counter,
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
        $ancestorCount   = $this->determineAncestorCount();
        $descendantCount = $this->determineDescendantCount();
        $node = $this->nodeMapper->loadAncestors($node, 'parent', $ancestorCount);
        $node = $this->nodeMapper->loadDescendants($node, 'parent', 'children', $descendantCount);
        $this->respondWithNode($node, $ancestorCount, $descendantCount);
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
        $ancestorCount         = $this->determineAncestorCount();
        $descendantCount       = $this->determineDescendantCount();
        $searchQuery           = trim($this->requestQuery->get('query') ?? '');
        $searchKeywords        = explode(',', $searchQuery);
        /** @var GenericCollection|Node\Model[] $resultsForAllKeywords */
        $resultsForAllKeywords = new GenericCollection(Node\Model::class);

        foreach ($searchKeywords as $searchKeyword) {
            $resultsForKeyword = $this->nodeMapper->findByKeyword(
                trim($searchKeyword),
                $this->determineLimit(),
                $this->determineOffset(),
                $ancestorCount,
                $descendantCount,
                $this->requestQuery->getBool('topLevelOnly', false),
                $this->requestQuery->getInt('parentId'),
                $this->requestQuery->getBool('enabled'),
                $this->requestQuery->getBool('includeWithoutCode'),
            );

            $resultsForAllKeywords = $resultsForAllKeywords->mergeUnique($resultsForKeyword, 'id');
        }

        Node\Mapper::sortNodes($resultsForAllKeywords, 0);

        // Translate results: also add all result counts
        // (for top and first descendant level only, to keep my sanity)
        $ids = [];
        foreach ($resultsForAllKeywords as $node) {
            $ids = array_merge($ids, $this->extractIdsFromNode($node));
        }
        $translatedResults = $resultsForAllKeywords->map(
            new ModelToArray(
                $this->routeGenerator,
                $this->authorizer,
                $this->getUser(),
                $ancestorCount,
                $descendantCount,
                $this->counter->findUsageCounts($ids),
            )
        );

        $this->response = $this->responseAsJson($translatedResults);
    }

    /**
     * Fetch an entire tree of curriculum nodes.
     *
     * @api   GET /api/curriculum/tree
     * @since 22.1
     */
    public function getTree(): void
    {
        $tree = $this->nodeMapper->getCollectionAsTree(
            $this->nodeMapper->findAll(),
            'parent',
            'children',
        );
        $this->response = $this->responseAsJson(
            $tree->map(
                new ModelToArray(
                    $this->routeGenerator,
                    $this->authorizer,
                    $this->getUser(),
                    0,
                    self::MAX_DESCENDANTS,
                    null,
                )
            )
        );
    }

    /**
     * Endpoint for creating a curriculum node.
     *
     * @api   POST /api/curriculum{/id}
     * @since 22.1
     *
     * @param Node\Model|null $node if set: set this node as the parent of the
     *                              new node
     * @return void
     * @throws MapperException if somehow the node can't be created
     */
    public function postNode(?Node\Model $node): void
    {
        $newNode = $node
            ? (new Node\Model())->setParent($node)
            : null;
        $this->respondWithNode($this->persistNode($newNode), 0, 0);
    }

    /**
     * Endpoint for updating a curriculum node.
     *
     * @api   PUT /api/curriculum/{id}
     * @since 22.1
     *
     * @param Node\Model $node
     * @return void
     * @throws MapperException if somehow the node can't be updated
     */
    public function putNode(Node\Model $node): void
    {
        $this->respondWithNode($this->persistNode((new Node\Model())->setId($node->getId())), 0, 0);
    }

    /**
     * Endpoint for partially updating a curriculum node.
     *
     * @api   PATCH /api/curriculum/{id}
     * @since 22.1
     *
     * @param Node\Model $node
     * @return void
     * @throws MapperException if somehow the node can't be updated
     */
    public function patchNode(Node\Model $node): void
    {
        $this->respondWithNode($this->persistNode($node), 0, 0);
    }

    /**
     * Endpoint for deleting a curriculum node.
     *
     * @param Node\Model $node
     * @return void
     * @throws MapperException if somehow the node can't be deleted
     * @api   DELETE /api/curriculum/{id}
     * @since 22.1
     *
     * @noinspection PhpDocMissingThrowsInspection - this can't throw an invalid with a 204
     */
    public function deleteNode(Node\Model $node): void
    {
        $this->nodeMapper->delete($node);
        $this->response = $this->responseEmpty(204);
    }

    /**
     * Persist and return a node, based on the contents of the request body and
     * the previous state of the node.
     *
     * @param Node\Model|null $previous If null, create a new node: otherwise,
     *                                  update the fields in `$previous`.
     * @return Node\Model
     * @throws MapperException if somehow the node can't be saved
     */
    private function persistNode(?Node\Model $previous): Node\Model
    {
        $data          = $this->request->getParsedBody();
        $translator    = new ArrayToModel();
        $persistedNode = $translator($data, $previous);
        $this->nodeMapper->save($persistedNode);
        return $persistedNode;
    }

    /**
     * Output a node in the response.
     *
     * @param Node\Model $node
     * @param int        $ancestorCount
     * @param int        $descendantCount
     * @return void
     */
    private function respondWithNode(Node\Model $node, int $ancestorCount, int $descendantCount): void
    {
        $childNodes = $node->getChildren();
        Node\Mapper::sortNodes($childNodes, 0);
        $node->setChildren($childNodes);
        $translator     = new ModelToArray(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            $ancestorCount,
            $descendantCount,
            $this->counter->findUsageCounts($this->extractIdsFromNode($node)),
        );
        $this->response = $this->responseAsJson($translator($node));
    }

    /**
     * How many levels of ancestors may this API retrieve?
     *
     * Bound to between 0 and `self::MAX_ANCESTORS`.
     *
     * @return int
     */
    private function determineAncestorCount(): int
    {
        return min(
            self::MAX_ANCESTORS,
            max(0, $this->requestQuery->getInt('ancestors', 0)),
        );
    }

    /**
     * How many levels of descendants may this API retrieve?
     *
     * Bound to between 0 and `self::MAX_DESCENDANTS`.
     *
     * @return int
     */
    private function determineDescendantCount(): int
    {
        return min(
            self::MAX_DESCENDANTS,
            max(0, $this->requestQuery->getInt('descendants', 0)),
        );
    }

    /**
     * How many results may this API retrieve?
     *
     * Bound to between 0 and `self::MAX_LIMIT`.
     *
     * @return int
     */
    private function determineLimit(): int
    {
        return min(
            self::MAX_LIMIT,
            max(self::MIN_LIMIT, $this->requestQuery->getInt('limit', self::DEFAULT_LIMIT)),
        );
    }

    /**
     * Which is the first result this API should retrieve?
     *
     * @return int
     */
    private function determineOffset(): int
    {
        return max(0, $this->requestQuery->getInt('offset', 0));
    }

    /**
     * Extract this node's IDs, as well as its parent and child IDs.
     *
     * @param Node\Model $node
     * @return array<int>
     */
    private function extractIdsFromNode(Node\Model $node): array
    {
        $ids = [$node->getId()];
        if ($node->getParent()?->getId()) {
            $ids[] = $node->getParent()->getId();
        }
        return array_merge($ids, $node->getChildren()->getPropertyValues('id'));
    }
}
