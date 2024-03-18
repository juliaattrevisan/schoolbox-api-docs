<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Usage\Folder;

use Alaress\Web\Controller\Base;
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
                    implode(',', $newNodes->getPropertyValues('id')),
                ),
                HttpResponse::HTTP_NOT_FOUND,
            );
            return;
        }

        $folder = $folder->setCurriculumNodes($newNodesFromDb);
        $this->folderMapper->save($folder);
        $this->response = $this->responseEmpty();
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
        $this->response = $this->responseEmpty();
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
        $this->response = $this->responseEmpty();
    }
}
