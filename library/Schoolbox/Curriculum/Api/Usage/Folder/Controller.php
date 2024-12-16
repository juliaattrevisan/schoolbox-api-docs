<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Usage\Folder;

use Alaress\Web\Controller\Base;
use Psr\Http\Message\ResponseInterface;
use Schoolbox\Curriculum\Api\Translator\ArrayToModel;
use Schoolbox\Curriculum\Node;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Controller actions for modifying curriculum API usage for folders only.
 */
class Controller extends Base
{
    private const MAX_ANCESTORS   = 50;
    private const MAX_DESCENDANTS = 50;

    /**
     * Constructor
     *
     * @param Folder\Mapper $folderMapper Folder mapper: used for saving changes to folder
     * @param Node\Mapper   $nodeMapper   Curriculum node mapper: used for verifying that nodes to be set on a folder
     *                                    actually exist
     */
    public function __construct(private Folder\Mapper $folderMapper, private Node\Mapper $nodeMapper)
    {
    }

    /**
     * Set curriculum nodes associated with a folder.
     *
     * @api POST /api/curriculum/usage/folder/{folderId}
     * @since 24.0
     *
     * @param Folder\Model $folder
     * @return void
     */
    public function postFolderUsage(Folder\Model $folder): void
    {
        $data           = $this->request->getParsedBody();
        $translator     = new ArrayToModel();
        $newNodes       = new GenericCollection(Node\Model::class, array_map($translator, $data));
        $newNodesFromDb = $this->nodeMapper->findByColumn('id', $newNodes->getPropertyValues('id'));

        // Verify that all new nodes from form exist in the DB:
        // raise error if any do not
        $newNodesFromFormNotInDb = $newNodes->diffBy($newNodesFromDb, 'id');
        if ($newNodesFromFormNotInDb->count() > 0) {
            $this->sendJsonError(
                sprintf(
                    'Not all curriculum nodes exist (missing nodes: %s)',
                    implode(',', $newNodesFromFormNotInDb->getPropertyValues('id')),
                ),
                HttpResponse::HTTP_NOT_FOUND,
            );
            return;
        }

        $folder = $folder->setCurriculumNodes($newNodesFromDb);
        $this->folderMapper->save($folder);
        $this->response = $this->buildResponse($folder);
    }

    /**
     * Add a single curriculum node to a folder.
     *
     * @api POST /api/curriculum/usage/{id}/folder/{folderId}
     * @since 24.0
     *
     * @param Node\Model   $node
     * @param Folder\Model $folder
     * @return void
     */
    public function postSingleFolderUsage(Node\Model $node, Folder\Model $folder): void
    {
        // Is $node in the folder already? Show conflict response if so
        if ($folder->getCurriculumNodes()->contains($node)) {
            $this->response = $this->responseEmpty(HttpResponse::HTTP_CONFLICT);
            return;
        }
        $folder = $folder->setCurriculumNodes($folder->getCurriculumNodes()->add($node));
        $this->folderMapper->save($folder);
        $this->response = $this->buildResponse($folder);
    }

    /**
     * Remove a single curriculum node from a folder.
     *
     * @api DELETE /api/curriculum/usage/{id}/folder/{folderId}
     * @since 24.0
     *
     * @param Node\Model   $node
     * @param Folder\Model $folder
     * @return void
     */
    public function deleteSingleFolderUsage(Node\Model $node, Folder\Model $folder): void
    {
        // Is $node not in the folder already? Show 404 not found if so
        if (!$folder->getCurriculumNodes()->contains($node)) {
            $this->response = $this->responseEmpty(HttpResponse::HTTP_NOT_FOUND);
            return;
        }
        $folder = $folder->setCurriculumNodes(
            $folder->getCurriculumNodes()->filter(fn (Node\Model $existing) => $existing->getId() !== $node->getId()),
        );
        $this->folderMapper->save($folder);
        $this->response = $this->buildResponse($folder);
    }

    /**
     * Returns a response representing the folder's curriculum nodes:
     * * a tree if query string value `returnFormat` is `tree`
     * * a flat structure otherwise
     *
     * @param Folder\Model $folder
     * @return ResponseInterface
     */
    private function buildResponse(Folder\Model $folder): ResponseInterface
    {
        return match (strtolower($this->requestQuery->get('returnFormat', ''))) {
            'tree' => $this->buildTreeResponse($folder),
            default => $this->buildFlatResponse($folder),
        };
    }

    /**
     * Returns a response representing the folder's curriculum nodes in a flat
     * structure.
     *
     * @param Folder\Model $folder
     * @return ResponseInterface
     */
    private function buildFlatResponse(Folder\Model $folder): ResponseInterface
    {
        return $this->responseAsJson($folder->getCurriculumNodes()->reindex());
    }

    /**
     * Returns a response representing the folder's curriculum nodes in a tree
     * structure.
     *
     * @param Folder\Model $folder
     * @return ResponseInterface
     */
    private function buildTreeResponse(Folder\Model $folder): ResponseInterface
    {
        // Note this logic is duplicated in CurriculumOverviewController::execute

        $directNodeIds = $folder->getCurriculumNodes()->getPropertyValues('id');

        // Fetch every node which is an ancestor or descendant of one of this folder's nodes, whether enabled or disabled.
        // A previously set curriculum node should remain on a folder even if the node has subsequently been disabled.
        $nodeIds = $this->nodeMapper->findFamilyTreeIds(
            $directNodeIds,
            'curriculum_node_parent_id',
            min(
                self::MAX_ANCESTORS,
                max(0, $this->requestQuery->getInt('ancestors', self::MAX_ANCESTORS)),
            ),
            min(
                self::MAX_DESCENDANTS,
                max(0, $this->requestQuery->getInt('descendants', self::MAX_DESCENDANTS)),
            ),
        );

        // Then find all the nodes and filter out disabled nodes that aren't directly mapped to this folder
        // This is because we still want to show disabled nodes if they are still directly mapped to aid in transitioning curriculum
        $curriculumNodes = $this->nodeMapper->findByColumnList(['id' => $nodeIds])->filter(
            fn ($node) => $node->getEnabled() || in_array($node->getId(), $directNodeIds)
        );

        // Then, sort the entire list of nodes into a tree structure.
        Node\Mapper::sortNodes($curriculumNodes, 0);
        $curriculumNodes = $this->nodeMapper->getCollectionAsTree($curriculumNodes);

        return $this->responseAsJson($curriculumNodes);
    }
}
