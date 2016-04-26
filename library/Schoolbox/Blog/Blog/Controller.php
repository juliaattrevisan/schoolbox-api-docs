<?php
/**
 * Controller for blogs
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 *
 */

namespace Schoolbox\Blog\Blog;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Blog\Image\Model as BlogImageModel;
use Schoolbox\Blog\Image\Mapper as BlogImageMapper;
use Schoolbox\Blog\Blog\Model as BlogModel;
use Schoolbox\Blog\Blog\Mapper as BlogMapper;
use Schoolbox\Blog\Comment\Model as BlogCommentModel;
use Schoolbox\Blog\Comment\Mapper as BlogCommentMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Mapper\Exception as MapperException;

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
            ->setMimeFilter('*/*')
            ->setReadOnly(false)
            ->setMulti(true);

        if ($attachments != null)
            foreach ($attachments as $attachment)
                $zone->attachFiles(array($attachment->getHash()));

        return $zone->getTwigContext();
    }

    /**
     * Save changes to existing record/Insert new record
     */
    public function postModify() {

        // Retrieve the record we are updating

        if (!empty($this->getRequest()->post["id"])) {

            // Updating an existing record

            /** @var BlogModel $record */
            $record = $this->getBlogMapper()->findById($this->getRequest()->post["id"]);
        } else {

            // Creating a new record

            /** @var BlogModel $record */
            $record = $this->getBlogMapper()->createModel();
            $record->setFolder($this->getRequest()->post["categoryId"]);
            $record->setOwner($this->getUser());
            $record->setCreatedAt(\Alaress_Date_Format::now());
        }

        $record->setBody($this->getRequest()->post["body"]);
        $record->setHeading($this->getRequest()->post["heading"]);

        $this->getBlogMapper()->save($record);

        // Delete any attachments if required

        // Keep track of attachments that aren't deleted so we don't add them again

        $retainedAttachments = array();

        // Get all attachments associated with this record

        try {
            $attachments = $this->getBlogImageMapper()->findByColumn("blog_id", $record->getId());
        } catch (\Exception $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        foreach ($attachments as $attachment) {

            $foundAttachment = false;

            if (is_array($this->getRequest()->post["attachment"])) {
                foreach ($this->getRequest()->post["attachment"] as $hash) {

                    $hash_array = explode(":", $hash);

                    if ($attachment->getHash() == $hash_array[1]) {
                        $foundAttachment = true;
                        break;
                    }
                }
            }

            if (!$foundAttachment) {

                // The attachment was found in the database, remove it

                $attachment->setUpdatedAt(\Alaress_Date_Format::now());
                $this->getBlogImageMapper()->delete($attachment);

            } else {

                // We are keeping this attachment

                $retainedAttachments[] = $attachment->getHash();
            }
        }

        // Save any new attachments

        if (is_array($this->getRequest()->post["attachment"])) {
            foreach ($this->getRequest()->post["attachment"] as $hash) {

                $hash_array = explode(":", $hash);

                // Only add attachments which don't already exist

                if (!in_array($hash_array[1], $retainedAttachments)) {
                    $attachment = new BlogImageModel();
                    $attachment->setBlogId($record->getId());
                    $attachment->setHash($hash_array[1]);
                    $attachment->setUpdatedAt(\Alaress_Date_Format::now());
                    $this->getBlogImageMapper()->save($attachment);
                }
            }
        }

        $this->getResponse()->redirect->afterPost("/blog/{$record->getId()}");

    }

    /**
     * Display selected item
     * @param $id
     */
    public function getItem($id)
    {
        try
        {
            /** @var BlogModel $record */
            $record = $this->getBlogMapper()->findById($id);

            /** @var FolderModel $folder */
            $folder = $this->getFolderMapper()->findById($record->getFolder()->getId());

            /** @var User $user */
            $user = $this->getUserMapper()->findById($record->getOwner()->getId());
            
            $record->setFolder($folder);
            $record->setOwner($user);

            $comments = $this->getBlogCommentMapper()->findByColumn("blog_id", $record->getId());

            /** @var BlogCommentModel $comment */
            foreach ($comments as $comment) {
                /** @var User $user */
                $user = $this->getUserMapper()->findById($comment->getOwner()->getId());
                $comment->setOwner($user);
            }

            $images = $this->getBlogImageMapper()->findByColumn("blog_id", $record->getId());
        }
        catch (MapperException $e)
        {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        // Lets get the party started...

        $this->getResponse()->content->set(
            array(
                'template' => '@blog/item.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'record'            => $record,
                    'comments'          => $comments,
                    'images'            => $images
                ),
            )
        );
    }

    /**
     * Create new blog comment
     * @throws MapperException
     */
    public function postCommentInsert() {
        $id = $this->getRequest()->post["id"];

        try {
            /** @var BlogModel $blog */
            $blog = $this->getBlogMapper()->findById($id);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        /** @var BlogCommentModel $comment */
        $comment = $this->getBlogCommentMapper()->createModel();

        $comment->setOwner($this->getUser());
        $comment->setBlog($blog);
        $comment->setBody($this->getRequest()->post["body"]);
        $comment->setCreatedAt(\Alaress_Date_Format::now());
        $this->getBlogCommentMapper()->save($comment);

        if (!empty($this->getRequest()->post["component"])) {
            $this->getResponse()->redirect->afterPost("/homepage/{$blog->getFolder()->getId()}");
        } else {
            $this->getResponse()->redirect->afterPost("/blog/{$blog->getId()}");
        }

    }


    /**
     * Delete blog comment
     * @throws MapperException
     */
    public function postCommentDelete() {

        try {
            /** @var BlogCommentModel $comment */
            $comment = $this->getBlogCommentMapper()->findById($this->getRequest()->post["id"]);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        // Only the owner of the comment is allowed to delete it

        if ($comment->getOwner()->getId() != $this->getUser()->getId() && !$this->getUser()->isSuperuser()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PERM_BLOG_MODIFY);
        }

        $returnUrl = "/blog/{$comment->getBlog()->getId()}";

        $this->getBlogCommentMapper()->delete($comment);
        $this->getResponse()->redirect->afterPost($returnUrl);
    }

    /**
     * Delete blog post
     * @throws MapperException
     */
    public function postDelete() {

        try {
            /** @var BlogModel $comment */
            $blog = $this->getBlogMapper()->findById($this->getRequest()->post["id"]);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        // Only the owner of the post is allowed to delete it

        if ($blog->getOwner()->getId() != $this->getUser()->getId() && !$this->getUser()->isSuperuser()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PERM_BLOG_MODIFY);
        }

        $returnUrl = "/homepage/{$blog->getFolder()->getId()}";

        $this->getBlogMapper()->delete($blog);
        $this->getResponse()->redirect->afterPost($returnUrl);
    }

    /**
     * Displays modify blog post page
     * @param $id
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getModify($id) {

        $record = $this->getBlogMapper()->findById($id);
        $record->setFolder($this->getFolderMapper()->findById($record->getFolder()->getId()));

        // Lets get the party started...

        $this->getResponse()->content->set(
            array(
                'template' => '@blog/modify.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'record'            => $record,
                    'WRITE_ACCESS'      => WRITE_ACCESS,
                    'READ_ACCESS'       => READ_ACCESS,
                    'FULL_ACCESS'       => FULL_ACCESS,
                    'attachzone'        => $this->getAttachZone($this->getBlogImageMapper()->findByColumn("blog_id", $id)),
                ),
            )
        );
    }

    /**
     * Display insert new blog post page
     * @param $id
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getInsert($id) {

        try {
            /** @var FolderModel $folder */
            $folder = $this->getFolderMapper()->findById($id);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        // Lets get the party started...

        $this->getResponse()->content->set(
            array(
                'template' => '@blog/modify.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'folder'            => $folder,
                    'WRITE_ACCESS'      => WRITE_ACCESS,
                    'READ_ACCESS'       => READ_ACCESS,
                    'FULL_ACCESS'       => FULL_ACCESS,
                    'attachzone'        => $this->getAttachZone(),
                ),
            )
        );
    }

    /**
     * Gets blog archive for the current calendar month
     * @param $id            The `folder_id` of the blog we want to view
     */
    public function getArchiveCurrent($id) {
        return $this->getArchive($id, date("Y"), date("n"));
    }

    /**
     * Display insert new blog post page
     * @param $id
     * @param $year         Archive year we are currently looking at (if not supplied will be based on current date)
     * @param $month        Archive month we are currently looking at (if not supplied will be based on current date)
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getArchive($id, $year, $month) {

        try {
            /** @var FolderModel $folder */
            $folder = $this->getFolderMapper()->findById($id);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        $blogs = $this->getBlogMapper()->findByColumn("folder_id", $id)->sortBy('createdAt', 'desc');
        $blogComments = [];

        $dateSelectorArray = [];
        $blogsSelectedMonth = [];

        // This creates an array of unique year/months for the date selector

        /** @var BlogModel $blog */
        foreach ($blogs as $blog) {
            $dateSelectorArray[$blog->getCreatedAt()->getYear() . "-" . $blog->getCreatedAt()->getMonth(true)] = $blog->getCreatedAt();
            $user = $this->getUserMapper()->findById($blog->getOwner()->getId());
            $blog->setOwner($user);

            if ($blog->getCreatedAt()->getYear() == $year && $blog->getCreatedAt()->getMonth() == $month) {

                $blogComments[$blog->getId()] = $this->getBlogCommentMapper()->findByColumn('blog_id', $blog->getId());

                /** @var BlogCommentModel $comment */
                foreach ($blogComments[$blog->getId()] as $comment) {
                    $comment->setOwner($this->getUserMapper()->findById($comment->getOwner()->getId()));
                }

                $blogsSelectedMonth[] = $blog;
            }
        }

        // Lets get the party started...

        $this->getResponse()->content->set(
            array(
                'template' => '@blog/archive.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'folder'            => $folder,
                    'blogs'             => $blogsSelectedMonth,
                    'blogComments'      => $blogComments,
                    'dateSelectorArray' => $dateSelectorArray,
                    'WRITE_ACCESS'      => WRITE_ACCESS,
                    'READ_ACCESS'       => READ_ACCESS,
                    'FULL_ACCESS'       => FULL_ACCESS,
                    'attachzone'        => $this->getAttachZone(),
                ),
            )
        );
    }

    /**
     * Here be the getters/setters, nothing to see here ¯\_(ツ)_/¯
     */

    /**
     * @param $userAccessMapper
     */
    public function setUserAccessMapper($userAccessMapper) {
        $this->userAccessMapper = $userAccessMapper;
    }

    /**
     * @return UserAccessMapper
     */
    public function getUserAccessMapper() {
        return $this->userAccessMapper;
    }

    /**
     * @param $roleMapper
     */
    public function setRoleMapper($roleMapper) {
        $this->roleMapper = $roleMapper;
    }

    /**
     * @return RoleMapper
     */
    public function getRoleMapper() {
        return $this->roleMapper;
    }

    /**
     * @param $userMapper
     */
    public function setUserMapper($userMapper) {
        $this->userMapper = $userMapper;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper() {
        return $this->userMapper;
    }

    /**
     * @param $errorMapper
     */
    public function setErrorMapper($errorMapper) {
        $this->errorMapper = $errorMapper;
    }

    /**
     * @return ErrorMapper
     */
    public function getErrorMapper() {
        return $this->errorMapper;
    }

    /**
     * @param $folderMapper
     */
    public function setFolderMapper($folderMapper) {
        $this->folderMapper = $folderMapper;
    }

    /**
     * @return FolderMapper
     */
    public function getFolderMapper() {
        return $this->folderMapper;
    }

    /**
     * @param $blogMapper
     */
    public function setBlogMapper($blogMapper) {
        $this->blogMapper = $blogMapper;
    }

    /**
     * @return BlogMapper
     */
    public function getBlogMapper() {
        return $this->blogMapper;
    }

    /**
     * @param $blogImageMapper
     */
    public function setBlogImageMapper($blogImageMapper) {
        $this->blogImageMapper = $blogImageMapper;
    }

    /**
     * @return BlogImageMapper
     */
    public function getBlogImageMapper() {
        return $this->blogImageMapper;
    }

    /**
     * @param $blogCommentMapper
     */
    public function setBlogCommentMapper($blogCommentMapper) {
        $this->blogCommentMapper = $blogCommentMapper;
    }

    /**
     * @return BlogCommentMapper
     */
    public function getBlogCommentMapper() {
        return $this->blogCommentMapper;
    }
}

/*
 * This code will self-destruct in 5 seconds...
 *
          ,--.!,
       __/   -*-
     ,d08b.  '|`
     0088MM
     `9MMP'
*/
