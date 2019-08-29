<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\MyFiles;


use Alaress\Color\Color;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource;
use Schoolbox\Resource\Browser\AbstractTreeBuilder;
use Schoolbox\Resource\File;
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
     */
    public function __construct(Folder\ILocator $locator, User $user)
    {
        parent::__construct($locator, 'myfile', 'My Files');

        $this->user = $user;
    }


    /**
     * Return the given folder's href
     *
     * @param IFolder $folder
     * @return string
     */
    protected function getFolderHref(IFolder $folder)
    {
        return '/my-files/' . $folder->getId();
    }


    /**
     * Return the given file's href
     *
     * @param Resource\AbstractFile $file
     * @return string
     */
    protected function getFileHref(Resource\AbstractFile $file)
    {
        return '/cms/sendMyFile.php?id=' . $file->getId();
    }


    /**
     * @param int $nodeId
     * @return Collection|IFolder[]
     */
    protected function getNodeFolders($nodeId)
    {
        /** @var Mapper $locator */
        $locator = $this->folderLocator;

        // TODO cache this
        return $locator->findByColumnList([
            'owner' => $this->user->getId(),
            'parent' => $nodeId,
        ]);
    }


    /**
     * @param int $nodeId
     * @param string $mime
     * @return Collection|Resource\AbstractFile[]
     */
    protected function getNodeFiles($nodeId, $mime = '*/*')
    {
        /** @var Mapper $locator */
        $locator = $this->fileLocator;

        return $this->filterMime($locator->findByColumnList([
            'owner' => $this->user->getId(),
            'parent' => $nodeId
        ]), $mime);
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
}
