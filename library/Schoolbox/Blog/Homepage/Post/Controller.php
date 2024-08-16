<?php

/**
 * This is the main controller for standard (non-eportfolio) blogs that handles all the CRUD operations
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 *
 */

namespace Schoolbox\Blog\Homepage\Post;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Blog\Homepage\Comment\Mapper as BlogCommentMapper;
use Schoolbox\Blog\Homepage\Comment\Model as BlogCommentModel;
use Schoolbox\Blog\Homepage\Image\Mapper as BlogImageMapper;
use Schoolbox\Blog\Homepage\Image\Model as BlogImageModel;
use Schoolbox\Blog\Homepage\Post\Mapper as BlogMapper;
use Schoolbox\Blog\Homepage\Post\Model as BlogModel;
use Schoolbox\Collection;
use Schoolbox\Column\Base as ColumnBase;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /**
     * @var RoleMapper
     */
    protected $roleMapper;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @var BlogMapper;
     */
    protected $blogMapper;

    /**
     * @var BlogCommentMapper;
     */
    protected $blogCommentMapper;

    /**
     * @var FileMapper;
     */
    protected $fileMapper;

    /**
     * @var FolderMapper;
     */
    protected $folderMapper;

    /**
     * @var BlogImageMapper
     */
    protected $blogImageMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        // Grab instances of all the mappers

        $this->setUserAccessMapper(UserAccessMapper::getInstance());
        $this->setRoleMapper(RoleMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setFolderMapper(FolderMapper::getInstance());
        $this->setFileMapper(FileMapper::getInstance());
        $this->setBlogMapper(BlogMapper::getInstance());
        $this->setBlogCommentMapper(BlogCommentMapper::getInstance());
        $this->setBlogImageMapper(BlogImageMapper::getInstance());
    }

    /**
     * Insert an attach zone into the template
     * @param Collection $attachments
     * @return array
     */
    private function getAttachZone($attachments = null)
    {
        $zone = new Zone('attachment');
        $zone
            ->setThumbnailSize(64)
            ->setModalTitle('Select images')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload images')
            ->setMimeFilter('image/*')
            ->setReadOnly(false)
            ->setMulti(true)
            ->attachFiles($attachments ?? []);

        return $zone->getTwigContext();
    }

    /**
     * POST request for creating a new record.
     * @param  FolderModel $folder the folder we're creating a record in
     * @throws MapperException
     */
    public function postInsert(FolderModel $folder)
    {
        /** @var BlogModel $blog */
        $blog = $this->getBlogMapper()->createModel();
        $blog->setFolder($folder);
        $blog->setOwner($this->getUser());
        $blog->setCreatedAt(\Alaress_Date_Format::now());
        $this->postModify($blog);
    }

    /**
     * Save changes to existing record/Insert new record
     * @param BlogModel $blog the blog post the user wants to modify
     */
    public function postModify(BlogModel $blog)
    {
        $post = $this->requestPost;

        $blog->setBody($this->requestPost["body"]);
        $blog->setHeading($this->requestPost["heading"]);
        $this->getBlogMapper()->save($blog);

        // Process image gallery attachments

        // Keep track of attachments that aren't deleted so we don't add them again

        $retainedAttachments = array();

        // Get all attachments associated with this record

        $attachments = $this->getBlogImageMapper()->findByColumn("blog_id", $blog->getId(), ['deleted' => true]);

        if (is_array($post["attachment"] ?? false)) {
            $storages = (new CollectorFactory())->build($post["attachment"])->getAll();

            /* Dedupe files that existed concurrently - you dragged file A into the dropzone
             * then (without having saved anything yet) dragged file A and B into the dropzone
             * You'll end up with files [A, A, B] instead of just [A, B] at the end.
             */
            $temp = [];

            foreach ($storages as $storage) {
                $temp[$storage->getHash()] = $storage;
            }

            $storages = array_values($temp);
        } else {
            $storages = [];
        }

        /** @var BlogImageModel $attachment */
        foreach ($attachments as $attachment) {
            $foundAttachment = false;
            foreach ($storages as $storage) {
                if ($storage->getHash() == $attachment->getHash()) {
                    $foundAttachment = true;
                    break;
                }
            }

            if (!$foundAttachment && !$attachment->isDeleted()) {
                // The attachment was found in the database, remove it
                $attachment->setUpdatedAt(\Alaress_Date_Format::now());
                $this->getBlogImageMapper()->delete($attachment);
            } elseif ($foundAttachment && $attachment->isDeleted()) {
                // The attachment was found but deleted in the database. Restore it
                $attachment->setDeletedAt(null);
                $this->getBlogImageMapper()->save($attachment);
                $retainedAttachments[] = $attachment->getHash();
            } else {
                // We are keeping this attachment
                $retainedAttachments[] = $attachment->getHash();
            }
        }

        /** @var \Schoolbox\Resource\Storage\Model $storage */
        foreach ($storages as $storage) {
            // Only add attachments which don't already exist and are actually images
            if ($storage->getMimetype()->isImage()) {
                if (!in_array($storage->getHash(), $retainedAttachments)) {
                    $attachment = new BlogImageModel();
                    $attachment->setBlogId($blog->getId());
                    $attachment->setStorage($storage);
                    $attachment->setUpdatedAt(\Alaress_Date_Format::now());
                    $this->getBlogImageMapper()->save($attachment);
                }
            }
        }

        // Redirect user page to blog detail page

        $this->response = $this->redirectAfterPost("/blog/{$blog->getId()}");
    }

    /**
     * Display detail page for selected blog post
     * @param BlogModel $blog the blog post the user wants to view
     */
    public function getItem(BlogModel $blog)
    {
        try {
            // Grab the record

            // Hydrate the owner and folder details

            /** @var FolderModel $folder */
            $folder = $this->getFolderMapper()->findById($blog->getFolder()->getId());

            /** @var User $user */
            $user = $this->getUserMapper()->findByIdWithFallback($blog->getOwner()->getId());

            $blog->setFolder($folder);
            $blog->setOwner($user);

            // Load in comments

            $comments = $this->getBlogCommentMapper()->findByColumn("blog_id", $blog->getId());

            /** @var BlogCommentModel $comment */
            foreach ($comments as $comment) {
                /** @var User $user */
                $user = $this->getUserMapper()->findByIdWithFallback($comment->getOwner()->getId());
                $comment->setOwner($user);
            }

            // Load in images

            $images = $this->getBlogImageMapper()->findByColumn("blog_id", $blog->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        // Lets get the party started...

        $this->response = $this->responseFromTemplate(
            '@blog/item.twig',
            [
                'session'           => $_SESSION,
                'record'            => $blog,
                'comments'          => $comments,
                'images'            => $images,
            ],
        );
    }

    /**
     * Create new blog comment
     * @param BlogModel $blog
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function postCommentInsert(BlogModel $blog)
    {
        $trimmedComment = trim($this->requestPost['commentBody']);

        // Comments must not be empty
        if (strlen($trimmedComment) === 0) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        if (strlen($trimmedComment) > ColumnBase::TEXT_MAX_LENGTH) {
            $trimmedComment = substr($trimmedComment, 0, ColumnBase::TEXT_MAX_LENGTH);
        }

        /** @var BlogCommentModel $comment */
        $comment = $this->getBlogCommentMapper()->createModel();
        $comment->setOwner($this->getUser());
        $comment->setBlog($blog);
        $comment->setBody($trimmedComment);
        $comment->setCreatedAt(\Alaress_Date_Format::now());

        $this->getBlogCommentMapper()->save($comment);

        $returnUrlEncoded = $this->requestPost['returnUrl'];

        if ($returnUrlEncoded !== null) {
            $returnUrl = Redirect::decode($returnUrlEncoded);
        } else {
            // If no return URL supplied, redirect to the Blog on which the comment was added.
            $returnUrl = $this->routeGenerator->generate('blog.getItem', ['id' => $blog->getId()]);
        }

        $this->response = $this->redirectAfterPost($returnUrl);
    }

    /**
     * Delete blog comment
     * @param BlogCommentModel $comment
     * @throws MapperException
     */
    public function postCommentDelete(BlogCommentModel $comment)
    {
        // Delete the comment
        $this->getBlogCommentMapper()->delete($comment);
    }

    /**
     * Delete blog post
     * @param BlogModel $blog
     * @throws MapperException
     */
    public function postDelete(BlogModel $blog)
    {
        // Delete the blog
        $this->getBlogMapper()->delete($blog);
    }

    /**
     * Displays modify blog post page
     * @param BlogModel $blog the blog post we are modifying
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getModify(BlogModel $blog)
    {
        $blog->setFolder($this->getFolderMapper()->findById($blog->getFolder()->getId()));

        // Lets get the party started...
        $this->response = $this->responseFromTemplate(
            '@blog/modify.twig',
            [
                'session'           => $_SESSION,
                'record'            => $blog,
                'WRITE_ACCESS'      => UserAccess::ACCESS_WRITE,
                'READ_ACCESS'       => UserAccess::ACCESS_READ,
                'FULL_ACCESS'       => UserAccess::ACCESS_FULL,
                'attachzone'        => $this->getAttachZone($this->getBlogImageMapper()->findByColumn("blog_id", $blog->getId())),
            ],
        );
    }

    /**
     * Display insert new blog post page
     * @param FolderModel $folder the folder into which we are inserting the new post
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getInsert(FolderModel $folder)
    {
        // Lets get the party started...
        $this->response = $this->responseFromTemplate(
            '@blog/modify.twig',
            [
                'session'           => $_SESSION,
                'folder'            => $folder,
                'WRITE_ACCESS'      => UserAccess::ACCESS_WRITE,
                'READ_ACCESS'       => UserAccess::ACCESS_READ,
                'FULL_ACCESS'       => UserAccess::ACCESS_FULL,
                'attachzone'        => $this->getAttachZone(),
            ],
        );
    }

    /**
     * Gets blog archive for the current calendar month
     * @param FolderModel $folder The ID of the folder whose blog archive we want to view
     */
    public function getArchiveCurrent(FolderModel $folder)
    {
        $this->getArchive($folder, date("Y"), date("n"));
    }

    /**
     * Gets blog archive for the selected calendar month
     * @param FolderModel $folder the folder whose blog archive we want to view
     * @param int $year     Archive year we are currently looking at (if not supplied will be based on current date)
     * @param int $month    Archive month we are currently looking at (if not supplied will be based on current date)
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getArchive(FolderModel $folder, $year, $month)
    {
        $blogs = $this->getBlogMapper()->findByColumn("folder_id", $folder->getId())->sortBy('createdAt', 'desc');
        $blogComments = [];
        $blogImages = [];

        $dateSelectorArray = [];
        $blogsSelectedMonth = [];

        // This creates an array of unique year/months for the date selector

        /** @var BlogModel $blog */
        foreach ($blogs as $blog) {
            $dateSelectorArray[$blog->getCreatedAt()->getYear() . "-" . $blog->getCreatedAt()->getMonth(true)] = $blog->getCreatedAt();
            $user = $this->getUserMapper()->findByIdWithFallback($blog->getOwner()->getId());
            $blog->setOwner($user);

            if ($blog->getCreatedAt()->getYear() == $year && $blog->getCreatedAt()->getMonth() == $month) {
                $blogComments[$blog->getId()] = $this->getBlogCommentMapper()->findByColumn('blog_id', $blog->getId());
                $blogImages[$blog->getId()] = $this->getBlogImageMapper()->findByColumn("blog_id", $blog->getId());

                /** @var BlogCommentModel $comment */
                foreach ($blogComments[$blog->getId()] as $comment) {
                    $comment->setOwner($this->getUserMapper()->findByIdWithFallback($comment->getOwner()->getId()));
                }

                $blogsSelectedMonth[] = $blog;
            }
        }

        // Lets get the party started...

        $this->response = $this->responseFromTemplate(
            '@blog/archive.twig',
            [
                'session'           => $_SESSION,
                'folder'            => $folder,
                'blogs'             => $blogsSelectedMonth,
                'blogImages'        => $blogImages,
                'blogComments'      => $blogComments,
                'dateSelectorArray' => $dateSelectorArray,
                'WRITE_ACCESS'      => UserAccess::ACCESS_WRITE,
                'READ_ACCESS'       => UserAccess::ACCESS_READ,
                'FULL_ACCESS'       => UserAccess::ACCESS_FULL,
                'attachzone'        => $this->getAttachZone(),
            ],
        );
    }

    /**
     * Here be the getters/setters, nothing to see here ¯\_(ツ)_/¯
     */

    /**
     * @param UserAccessMapper $userAccessMapper
     */
    public function setUserAccessMapper($userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
    }

    /**
     * @return UserAccessMapper
     */
    public function getUserAccessMapper()
    {
        return $this->userAccessMapper;
    }

    /**
     * @param RoleMapper $roleMapper
     */
    public function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
    }

    /**
     * @return RoleMapper
     */
    public function getRoleMapper()
    {
        return $this->roleMapper;
    }

    /**
     * @param UserMapper $userMapper
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     */
    public function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }

    /**
     * @return ErrorMapper
     */
    public function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param FolderMapper $folderMapper
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }

    /**
     * @return FolderMapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param FileMapper $fileMapper
     */
    public function setFileMapper($fileMapper)
    {
        $this->fileMapper = $fileMapper;
    }

    /**
     * @return FileMapper
     */
    public function getFileMapper()
    {
        return $this->fileMapper;
    }

    /**
     * @param BlogMapper $blogMapper
     */
    public function setBlogMapper($blogMapper)
    {
        $this->blogMapper = $blogMapper;
    }

    /**
     * @return BlogMapper
     */
    public function getBlogMapper()
    {
        return $this->blogMapper;
    }

    /**
     * @param BlogImageMapper $blogImageMapper
     */
    public function setBlogImageMapper($blogImageMapper)
    {
        $this->blogImageMapper = $blogImageMapper;
    }

    /**
     * @return BlogImageMapper
     */
    public function getBlogImageMapper()
    {
        return $this->blogImageMapper;
    }

    /**
     * @param BlogCommentMapper $blogCommentMapper
     */
    public function setBlogCommentMapper($blogCommentMapper)
    {
        $this->blogCommentMapper = $blogCommentMapper;
    }

    /**
     * @return BlogCommentMapper
     */
    public function getBlogCommentMapper()
    {
        return $this->blogCommentMapper;
    }
}
