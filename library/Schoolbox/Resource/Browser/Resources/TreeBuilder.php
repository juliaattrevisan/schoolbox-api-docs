<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Resources;

use Alaress\Color\Color;
use Schoolbox\Collection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource;
use Schoolbox\Resource\Browser\AbstractTreeBuilder;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\User;


class TreeBuilder extends AbstractTreeBuilder
{
    /** @var User */
    protected $user;

    /**
     * @param Folder\ILocator $locator
     * @param User $user
     * @param string $rootLabel
     */
    public function __construct(Folder\ILocator $locator, User $user, $rootLabel = 'Resources')
    {
        parent::__construct($locator, 'sbfsfile', $rootLabel);

        $this->user = $user;
    }


    /**
     * Return the folder type for the given node. By default is simply 'folder'.
     *
     * @param IFolder|Folder\Model $folder
     * @return string
     */
    public function getNodeType(IFolder $folder): string
    {
        return $folder->getNodeType();
    }


    /**
     * Return the given folder's name. By default is just its name...
     *
     * @param IFolder|Folder\Model $folder
     * @return string
     */
    protected function getFolderName(IFolder $folder)
    {
        // Build the name of the category
        return $folder->getTitle();
    }


    /**
     * Return the given folder's href
     *
     * @param IFolder $folder
     * @return string
     */
    protected function getFolderHref(IFolder $folder)
    {
        return '/homepage/' . $folder->getId();
    }


    /**
     * Return the given file's href
     *
     * @param Resource\AbstractFile $file
     * @return string
     */
    protected function getFileHref(Resource\AbstractFile $file)
    {
        return '/send.php?id=' . $file->getId();
    }


    /**
     * Return the given folder's colour
     *
     * @param IFolder $folder
     * @return null|Color
     */
    protected function getFolderColour(IFolder $folder): ?Color
    {
        return $folder->getColour();
    }


    /**
     * Return the given folder's cover image
     *
     * @param IFolder $folder
     * @return string
     */
    protected function getFolderCoverImage(IFolder $folder)
    {
        return $folder->getCoverImage();
    }


    /**
     * @param int $folderId
     * @return string
     */
    protected function getFolderPermission($folderId)
    {
        return $this->getPermissionString(Access::getInstance()->getFolderAccess($this->user, $folderId));
    }


    /**
     * @param  Resource\AbstractFile $file
     * @return string
     */
    protected function getFilePermission($file)
    {
        if ($this->user->isSuperuser()) {
            return $this->getPermissionString(UserAccess::ACCESS_FULL);
        }

        if (!$this->fileLocator) {
            return $this->getPermissionString(UserAccess::ACCESS_DENY);
        }

        // retrieve the folder permission
        $folderPerm = $this->getFolderPermission($file->getFolder()->getId());

        // if read and hidden, cast to none; otherwise leave as-is
        if ($folderPerm == $this->getPermissionString(UserAccess::ACCESS_READ)
            && $file->isHidden()
        ) {
            $folderPerm = $this->getPermissionString(UserAccess::ACCESS_DENY);
        }

        return $folderPerm;
    }


    /**
     * @param int $nodeId
     * @return Collection|IFolder[]
     */
    protected function getNodeFolders($nodeId)
    {
        // TODO cache this
        return $this->folderLocator->findByParentId($nodeId);
    }


    /**
     * @param int $nodeId
     * @param string $mime
     * @return Collection|Resource\AbstractFile[]
     */
    protected function getNodeFiles($nodeId, $mime = '*/*')
    {
        // TODO cache this
        if ($this->fileLocator) {
            return $this->filterMime($this->fileLocator->findByFolderId($nodeId), $mime);
        }
        return new Collection();
    }


    /**
     * @param Resource\AbstractFile|Resource\Link $file
     * @throws Resource\Exception
     * @return array
     */
    protected function buildFileNode($file)
    {
        $permission = $this->getFilePermission($file);
        if (empty($permission)) {
            throw new Resource\Exception("No permission for this node");
        }
        if ($file instanceof Resource\AbstractFile) {
            return $this->generateFileArray(
                $file,
                $file->getTitle(),
                [
                    'data-permission' => $permission,
                    'data-type' => 'file'
                ],
                [
                    'href' => $this->getFileHref($file),
                ]
            );
        } elseif ($file instanceof Resource\Link) {
            return $this->generateNodeArray(
                $file->getId(),
                $file->getFolder()->getId(),
                'link',
                $file->getDescription(),
                null,
                null,
                [
                    'data-id' => $file->getId(),
                    'data-permission' => $permission,
                    'data-type' => 'link'
                ],
                [
                    'href' => $file->getHref(),
                ]
            );
        } else {
            return [];
        }
    }
}
