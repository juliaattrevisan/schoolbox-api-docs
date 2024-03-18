<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Usage\Folder;

use Alaress\Web\Controller\Base;
use Schoolbox\Curriculum\Node;
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
     */
    public function __construct(private Folder\Mapper $folderMapper)
    {
    }

    /**
     * Add a curriculum node to a folder.
     *
     * @api POST /api/curriculum/usage/{id}/folder/{folderId}
     * @since 24.0
     *
     * @param Node\Model   $node
     * @param Folder\Model $folder
     * @return void
     */
    public function postFolderUsage(Node\Model $node, Folder\Model $folder): void
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
     * Remove a curriculum node to a folder.
     *
     * @api DELETE /api/curriculum/usage/{id}/folder/{folderId}
     * @since 24.0
     *
     * @param Node\Model   $node
     * @param Folder\Model $folder
     * @return void
     */
    public function deleteFolderUsage(Node\Model $node, Folder\Model $folder): void
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
