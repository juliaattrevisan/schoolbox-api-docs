<?php
/**
 * Controller for blogs
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 *
 */

namespace Schoolbox\Blog\EportfolioBlog;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Blog\EportfolioImage\Model as EportfolioBlogImageModel;
use Schoolbox\Blog\EportfolioImage\Mapper as EportfolioBlogImageMapper;
use Schoolbox\Blog\EportfolioBlog\Model as EportfolioBlogModel;
use Schoolbox\Blog\EportfolioBlog\Mapper as EportfolioBlogMapper;
use Schoolbox\Blog\EportfolioComment\Model as EportfolioBlogCommentModel;
use Schoolbox\Blog\EportfolioComment\Mapper as EportfolioBlogCommentMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Mapper\Exception as MapperException;
use Eportfolio\PageMapper as EportfolioPageMapper;

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
     * @var EportfolioBlogMapper;
     */
    protected $blogMapper;

    /**
     * @var EportfolioBlogCommentMapper;
     */
    protected $blogCommentMapper;

    /**
     * @var FolderMapper;
     */
    protected $folderMapper;

    /**
     * @var EportfolioBlogImageMapper
     */
    protected $blogImageMapper;

    /**
     * @var EportfolioEportfolioPageMapper
     */
    protected $eportfolioPageMapper;

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
        $this->setEportfolioBlogMapper(EportfolioBlogMapper::getInstance());
        $this->setEportfolioBlogCommentMapper(EportfolioBlogCommentMapper::getInstance());
        $this->setEportfolioBlogImageMapper(EportfolioBlogImageMapper::getInstance());
        $this->setEportfolioPageMapper(EportfolioPageMapper::getInstance());
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

            /** @var EportfolioBlogModel $record */
            $record = $this->getEportfolioBlogMapper()->findById($this->getRequest()->post["id"]);
        } else {

            // Creating a new record

            $user = $this->getUserMapper()->findById($this->getRequest()->post["ownerId"]);

            /** @var EportfolioBlogModel $record */
            $record = $this->getEportfolioBlogMapper()->createModel();
            $record->setOwner($user);
            $record->setPageId($this->getRequest()->post["pageId"]);
            $record->setCreatedAt(\Alaress_Date_Format::now());
        }

        $record->setBody($this->getRequest()->post["body"]);
        $record->setHeading($this->getRequest()->post["heading"]);

        $this->getEportfolioBlogMapper()->save($record);

        // Delete any attachments if required

        // Keep track of attachments that aren't deleted so we don't add them again

        $retainedAttachments = array();

        // Get all attachments associated with this record

        try {
            $attachments = $this->getEportfolioBlogImageMapper()->findByColumn("blog_id", $record->getId());
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
                $this->getEportfolioBlogImageMapper()->delete($attachment);

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
                    $attachment = new EportfolioBlogImageModel();
                    $attachment->setBlogId($record->getId());
                    $attachment->setHash($hash_array[1]);
                    $attachment->setUpdatedAt(\Alaress_Date_Format::now());
                    $this->getEportfolioBlogImageMapper()->save($attachment);
                }
            }
        }

        $this->getResponse()->redirect->afterPost("/eportfolio/" . $this->getRequest()->post["ownerId"] . "/" . $this->getRequest()->post["pageId"]);

    }

    /**
     * Display selected blog post
     * @param int $ownerId      The owner of the eportfolio we are viewing
     * @param int $pageId       The page on the eportfolio we are viewing
     * @param int $id           The id of blog article we are viewing
     */
    public function getItem($ownerId, $pageId, $id)
    {
        $page = $this->getEportfolioPageMapper()->findById($pageId);

        try
        {
            /** @var EportfolioBlogModel $record */
            $record = $this->getEportfolioBlogMapper()->findById($id);

            /** @var User $user */
            $user = $this->getUserMapper()->findById($record->getOwner()->getId());
            $record->setOwner($user);

            $comments = $this->getEportfolioBlogCommentMapper()->findByColumn("blog_id", $record->getId());

            /** @var EportfolioBlogCommentModel $comment */
            foreach ($comments as $comment) {
                /** @var User $user */
                $user = $this->getUserMapper()->findById($comment->getOwner()->getId());
                $comment->setOwner($user);
            }

            $images = $this->getEportfolioBlogImageMapper()->findByColumn("blog_id", $record->getId());
        }
        catch (MapperException $e)
        {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        // Lets get the party started...

        $this->getResponse()->content->set(
            array(
                'template' => '@eportfolioBlog/item.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'record'            => $record,
                    'comments'          => $comments,
                    'images'            => $images,
                    'page'              => $page
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
        $pageId = $this->getRequest()->post["pageId"];
        $ownerId = $this->getRequest()->post["ownerId"];

        try {
            /** @var EportfolioBlogModel $blog */
            $blog = $this->getEportfolioBlogMapper()->findById($id);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        /** @var EportfolioBlogCommentModel $comment */
        $comment = $this->getEportfolioBlogCommentMapper()->createModel();

        $comment->setOwner($this->getUser());
        $comment->setBlog($blog);
        $comment->setBody($this->getRequest()->post["body"]);
        $comment->setCreatedAt(\Alaress_Date_Format::now());
        $this->getEportfolioBlogCommentMapper()->save($comment);

        $this->getResponse()->redirect->afterPost("/eportfolio/{$ownerId}/{$pageId}");
    }


    /**
     * Delete blog comment
     * @throws MapperException
     */
    public function postCommentDelete() {

        try {
            /** @var EportfolioBlogCommentModel $comment */
            $comment = $this->getEportfolioBlogCommentMapper()->findById($this->getRequest()->post["id"]);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        // Only the owner of the comment is allowed to delete it

        if ($comment->getOwner()->getId() != $this->getUser()->getId() && !$this->getUser()->isSuperuser()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PERM_BLOG_MODIFY);
        }

        $this->getEportfolioBlogCommentMapper()->delete($comment);
    }

    /**
     * Delete blog post
     * @throws MapperException
     */
    public function postDelete() {

        $id = $this->getRequest()->post["id"];

        try {
            /** @var EportfolioBlogModel $comment */
            $blog = $this->getEportfolioBlogMapper()->findById($id);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        // Only the owner of the post is allowed to delete it

        if ($blog->getOwner()->getId() != $this->getUser()->getId() && !$this->getUser()->isSuperuser()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PERM_BLOG_MODIFY);
        }

        $this->getEportfolioBlogMapper()->delete($blog);
    }

    /**
     * Displays modify blog post page
     * @param $id
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getModify($ownerId, $pageId, $id) {
        $page = $this->getEportfolioPageMapper()->findById($pageId);

        $record = $this->getEportfolioBlogMapper()->findById($id);

        // Lets get the party started...

        $this->getResponse()->content->set(
            array(
                'template' => '@eportfolioBlog/modify.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'record'            => $record,
                    'WRITE_ACCESS'      => WRITE_ACCESS,
                    'READ_ACCESS'       => READ_ACCESS,
                    'FULL_ACCESS'       => FULL_ACCESS,
                    'attachzone'        => $this->getAttachZone($this->getEportfolioBlogImageMapper()->findByColumn("blog_id", $id)),
                    'page'              => $page
                ),
            )
        );
    }

    /**
     * Display insert new blog post page
     * @param $id
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getInsert($ownerId, $pageId) {
        $page = $this->getEportfolioPageMapper()->findById($pageId);

        // Lets get the party started...

        $this->getResponse()->content->set(
            array(
                'template' => '@eportfolioBlog/modify.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'WRITE_ACCESS'      => WRITE_ACCESS,
                    'READ_ACCESS'       => READ_ACCESS,
                    'FULL_ACCESS'       => FULL_ACCESS,
                    'attachzone'        => $this->getAttachZone(),
                    'page'              => $page
                ),
            )
        );
    }

    /**
     * Gets blog archive for the current calendar month
     * @param int $id            The `folder_id` of the blog we want to view
     */
    public function getArchiveCurrent($id) {
        return $this->getArchive($id, date("Y"), date("n"));
    }

    /**
     * Display insert new blog post page
     * @param int $id
     * @param int $year         Archive year we are currently looking at (if not supplied will be based on current date)
     * @param int $month        Archive month we are currently looking at (if not supplied will be based on current date)
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getArchive($id, $year, $month) {

        try {
            /** @var FolderModel $folder */
            $folder = $this->getFolderMapper()->findById($id);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        $blogs = $this->getEportfolioBlogMapper()->findByColumn("folder_id", $id)->sortBy('createdAt', 'desc');
        $blogComments = [];

        $dateSelectorArray = [];
        $blogsSelectedMonth = [];

        // This creates an array of unique year/months for the date selector

        /** @var EportfolioBlogModel $blog */
        foreach ($blogs as $blog) {
            $dateSelectorArray[$blog->getCreatedAt()->getYear() . "-" . $blog->getCreatedAt()->getMonth(true)] = $blog->getCreatedAt();
            $user = $this->getUserMapper()->findById($blog->getOwner()->getId());
            $blog->setOwner($user);

            if ($blog->getCreatedAt()->getYear() == $year && $blog->getCreatedAt()->getMonth() == $month) {

                $blogComments[$blog->getId()] = $this->getEportfolioBlogCommentMapper()->findByColumn('blog_id', $blog->getId());

                /** @var EportfolioBlogCommentModel $comment */
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
    public function setEportfolioBlogMapper($blogMapper) {
        $this->blogMapper = $blogMapper;
    }

    /**
     * @return EportfolioBlogMapper
     */
    public function getEportfolioBlogMapper() {
        return $this->blogMapper;
    }

    /**
     * @param $blogImageMapper
     */
    public function setEportfolioBlogImageMapper($blogImageMapper) {
        $this->blogImageMapper = $blogImageMapper;
    }

    /**
     * @return EportfolioBlogImageMapper
     */
    public function getEportfolioBlogImageMapper() {
        return $this->blogImageMapper;
    }

    /**
     * @param $blogCommentMapper
     */
    public function setEportfolioBlogCommentMapper($blogCommentMapper) {
        $this->blogCommentMapper = $blogCommentMapper;
    }

    /**
     * @return EportfolioBlogCommentMapper
     */
    public function getEportfolioBlogCommentMapper() {
        return $this->blogCommentMapper;
    }

    /**
     * @param EportfolioPageMapper $eportfolioPageMapper
     */
    public function setEportfolioPageMapper($eportfolioPageMapper) {
        $this->eportfolioPageMapper = $eportfolioPageMapper;
    }

    /**
     * @return EportfolioPageMapper
     */
    public function getEportfolioPageMapper() {
        return $this->eportfolioPageMapper;
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
