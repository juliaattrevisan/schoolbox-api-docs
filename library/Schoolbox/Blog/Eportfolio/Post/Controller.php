<?php

/**
 *
 * This is the main controller for e-portfolio blogs that handles all the CRUD operations
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Blog\Eportfolio\Post;

use Alaress\Web\Controller\Base;
use Eportfolio\Page as EportfolioPageModel;
use Eportfolio\PageMapper as EportfolioPageMapper;
use Schoolbox\Blog\Eportfolio\Comment\Mapper as EportfolioBlogCommentMapper;
use Schoolbox\Blog\Eportfolio\Comment\Model as EportfolioBlogCommentModel;
use Schoolbox\Blog\Eportfolio\Image\Mapper as EportfolioBlogImageMapper;
use Schoolbox\Blog\Eportfolio\Image\Model as EportfolioBlogImageModel;
use Schoolbox\Blog\Eportfolio\Post\Mapper as EportfolioBlogMapper;
use Schoolbox\Blog\Eportfolio\Post\Model as EportfolioBlogModel;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

class Controller extends Base
{

    /**
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /**
     * @var FileMapper;
     */
    protected $fileMapper;

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
     * @var EportfolioBlogImageMapper
     */
    protected $blogImageMapper;

    /**
     * @var EportfolioPageMapper
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
        $this->setFileMapper(FileMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
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
    private function getAttachZone(Collection $attachments)
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
            ->attachFiles($attachments);

        return $zone->getTwigContext();
    }

    /**
     * Insert new record
     *
     * @param EportfolioPageModel $page
     */
    public function postInsert(EportfolioPageModel $page)
    {
        // Inserting new record, create an object and shunt in the required values
        /** @var EportfolioBlogModel $record */
        $record = $this->getEportfolioBlogMapper()->createModel();
        $record->setOwner($this->getUser());
        $record->setPage($page);
        $this->postModify($page, $record);
    }

    /**
     * Save changes to existing record/Insert new record
     *
     * @param EportfolioBlogModel $blog
     */
    public function postModify(EportfolioPageModel $page, EportfolioBlogModel $blog)
    {

        // Update details and save the record
        $blog->setBody($this->getRequest()->post->get("body"));
        $blog->setHeading($this->getRequest()->post->get("heading"));
        $this->getEportfolioBlogMapper()->save($blog);

        // Update image gallery pictures

        // Keep track of attachments that aren't deleted so we don't add them again

        $retainedAttachments = array();

        // Get all attachments associated with this record

        try {
            $attachments = $this->getEportfolioBlogImageMapper()->findByColumn("blog_id", $blog->getId());
        } catch (\Exception $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        if (is_array($this->getRequest()->post->get("attachment"))) {
            $storages = (new CollectorFactory())->build($this->getRequest()->post->get('attachment'))->getAll();

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

        foreach ($attachments as $attachment) {
            $foundAttachment = false;
            foreach ($storages as $storage) {
                if ($storage->getHash() == $attachment->getHash()) {
                    $foundAttachment = true;
                    break;
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
        foreach ($storages as $storage) {
            // Only add attachments which don't already exist
            if (!in_array($storage->getHash(), $retainedAttachments)) {
                $attachment = new EportfolioBlogImageModel();
                $attachment->setBlogId($blog->getId());
                $attachment->setStorage($storage);
                $attachment->setUpdatedAt(\Alaress_Date_Format::now());
                $this->getEportfolioBlogImageMapper()->save($attachment);
            }
        }

        // Send them back to the item detail page

        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate(
                'eportfolioBlog.getItem',
                ['pageId' => $page->getPageId(), 'id' => $blog->getId(),]
            )
        );
    }

    /**
     * Display selected blog post
     * @param EportfolioPageModel $page
     * @param EportfolioBlogModel $blog
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getItem(EportfolioPageModel $page, EportfolioBlogModel $blog)
    {
        try {
            // Load any comments and hydrate their owners
            $comments = $this->getEportfolioBlogCommentMapper()->findByColumn("blog_id", $blog->getId());

            // Load any images
            $images = $this->getEportfolioBlogImageMapper()->findByColumn("blog_id", $blog->getId());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        // Lets get the party started...

        $this->getResponse()->content->set(
            array(
                'template' => '@eportfolioBlog/item.twig',
                'data'     => array(
                    'record'            => $blog,
                    'comments'          => $comments,
                    'images'            => $images,
                    'page'              => $page,
                    'pageOwner'         => $this->getUserMapper()->findById($page->getOwner()->getId()),
                ),
            )
        );
    }

    /**
     * Create new blog comment
     * @param EportfolioBlogModel $blog
     * @throws MapperException
     */
    public function postCommentInsert(EportfolioBlogModel $blog)
    {
        // Comments must not be empty
        if (strlen(trim($this->getRequest()->post["commentBody"]))) {
            // Create the comment and save

            /** @var EportfolioBlogCommentModel $comment */
            $comment = $this->getEportfolioBlogCommentMapper()->createModel();

            $comment->setOwner($this->getUser());
            $comment->setBlog($blog);
            $comment->setBody($this->getRequest()->post['commentBody']);
            $comment->setCreatedAt(\Alaress_Date_Format::now());

            $this->getEportfolioBlogCommentMapper()->save($comment);
        } else {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate(
                'eportfolioBlog.getItem',
                ['pageId' => $blog->getPage()->getPageId(), 'id' => $blog->getId(),]
            )
        );
    }


    /**
     * Delete blog comment
     * @param EportfolioBlogCommentModel $comment
     * @throws MapperException
     */
    public function postCommentDelete(EportfolioBlogCommentModel $comment)
    {
        $this->getEportfolioBlogCommentMapper()->delete($comment);
    }

    /**
     * Delete blog post
     * @param EportfolioBlogModel $blog
     * @throws MapperException
     */
    public function postDelete(EportfolioBlogModel $blog)
    {
        $this->getEportfolioBlogMapper()->delete($blog);
    }

    /**
     * Displays modify blog post page
     * @param EportfolioPageModel $page
     * @param EportfolioBlogModel $blog
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getModify(EportfolioPageModel $page, EportfolioBlogModel $blog)
    {
        // Lets get the party started...
        $this->getResponse()->content->set(
            array(
                'template' => '@eportfolioBlog/modify.twig',
                'data'     => array(
                    'record'            => $blog,
                    'WRITE_ACCESS'      => UserAccess::ACCESS_WRITE,
                    'READ_ACCESS'       => UserAccess::ACCESS_READ,
                    'FULL_ACCESS'       => UserAccess::ACCESS_FULL,
                    'attachzone'        => $this->getAttachZone($this->getEportfolioBlogImageMapper()->findByColumn("blog_id", $blog->getId())),
                    'page'              => $page,
                    'pageOwner'         => $this->userMapper->findById($page->getOwner()->getId()),
                ),
            )
        );
    }

    /**
     * Display insert new blog post page
     * @param EportfolioPageModel $page
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getInsert(EportfolioPageModel $page)
    {
        // Lets get the party started...
        $this->getResponse()->content->set(
            array(
                'template' => '@eportfolioBlog/modify.twig',
                'data'     => array(
                    'WRITE_ACCESS'      => UserAccess::ACCESS_WRITE,
                    'READ_ACCESS'       => UserAccess::ACCESS_READ,
                    'FULL_ACCESS'       => UserAccess::ACCESS_FULL,
                    'attachzone'        => $this->getAttachZone(new Collection()),
                    'page'              => $page,
                    'pageOwner'         => $this->userMapper->findById($page->getOwner()->getId()),
                ),
            )
        );
    }

    /**
     * Gets blog archive for the current calendar month
     * @param EportfolioPageModel $page
     */
    public function getArchiveCurrent(EportfolioPageModel $page)
    {
        $this->getArchive($page, date("Y"), date("n"));
    }

    /**
     * Gets blog archive for the selected calendar month
     * @param EportfolioPageModel $page  the e-portfolio page of the blog into which we are inserting the new blog psot
     * @param int                 $year  Archive year we are currently looking at (if not supplied will be based on current date)
     * @param int                 $month Archive month we are currently looking at (if not supplied will be based on current date)
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getArchive(EportfolioPageModel $page, $year, $month)
    {
        $blogs = $this->getEportfolioBlogMapper()->findByColumnList([
            "page_id" => $page->getPageId(),
        ])->sortBy('createdAt', 'desc');

        $blogComments = [];
        $blogImages = [];

        $dateSelectorArray = [];
        $blogsSelectedMonth = [];

        // This creates an array of unique year/months for the date selector


        /** @var EportfolioBlogModel $blog */
        foreach ($blogs as $blog) {
            $dateSelectorArray[$blog->getCreatedAt()->getYear() . "-" . $blog->getCreatedAt()->getMonth(true)] = $blog->getCreatedAt();

            if ($blog->getCreatedAt()->getYear() == $year && $blog->getCreatedAt()->getMonth() == $month) {
                $blogComments[$blog->getId()] = $this->getEportfolioBlogCommentMapper()->findByColumn('blog_id', $blog->getId());
                $blogImages[$blog->getId()] = $this->getEportfolioBlogImageMapper()->findByColumn('blog_id', $blog->getId());

                $blogsSelectedMonth[] = $blog;
            }
        }

        // Lets get the party started...
        $this->getResponse()->content->set(
            array(
                'template' => '@eportfolioBlog/archive.twig',
                'data'     => array(
                    'blogs'             => $blogsSelectedMonth,
                    'blogImages'        => $blogImages,
                    'blogComments'      => $blogComments,
                    'dateSelectorArray' => $dateSelectorArray,
                    'page'              => $page,
                    'pageOwner'         => $this->userMapper->findById($page->getOwner()->getId()),
                ),
            )
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
     * @param EportfolioBlogMapper $blogMapper
     */
    public function setEportfolioBlogMapper($blogMapper)
    {
        $this->blogMapper = $blogMapper;
    }

    /**
     * @return EportfolioBlogMapper
     */
    public function getEportfolioBlogMapper()
    {
        return $this->blogMapper;
    }

    /**
     * @param EportfolioBlogImageMapper $blogImageMapper
     */
    public function setEportfolioBlogImageMapper($blogImageMapper)
    {
        $this->blogImageMapper = $blogImageMapper;
    }

    /**
     * @return EportfolioBlogImageMapper
     */
    public function getEportfolioBlogImageMapper()
    {
        return $this->blogImageMapper;
    }

    /**
     * @param EportfolioBlogCommentMapper $blogCommentMapper
     */
    public function setEportfolioBlogCommentMapper($blogCommentMapper)
    {
        $this->blogCommentMapper = $blogCommentMapper;
    }

    /**
     * @return EportfolioBlogCommentMapper
     */
    public function getEportfolioBlogCommentMapper()
    {
        return $this->blogCommentMapper;
    }

    /**
     * @param EportfolioPageMapper $eportfolioPageMapper
     */
    public function setEportfolioPageMapper($eportfolioPageMapper)
    {
        $this->eportfolioPageMapper = $eportfolioPageMapper;
    }

    /**
     * @return EportfolioPageMapper
     */
    public function getEportfolioPageMapper()
    {
        return $this->eportfolioPageMapper;
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
}
