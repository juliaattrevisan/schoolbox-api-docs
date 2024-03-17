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

/**
 * Controller actions for modifying curriculum API usage for folders only.
 */
class Controller extends Base
{
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
    }
}
