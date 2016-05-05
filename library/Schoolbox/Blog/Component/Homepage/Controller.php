<?php
/**
 * E-portfolio blog component controller. Is responsible for rendering the TWIG into component
 * on e-portfolio pages
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Blog\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Blog\Homepage\Comment\Mapper as BlogCommentMapper;
use Schoolbox\Blog\Homepage\Comment\Model as BlogCommentModel;
use Schoolbox\Blog\Homepage\Image\Mapper as BlogImageMapper;
use Schoolbox\Blog\Homepage\Post\Mapper as BlogMapper;
use Schoolbox\Blog\Homepage\Post\Model as BlogModel;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;
use Schoolbox\UserMapper;
use SitePage;

class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     */
    public function __construct(
        Component_Instance $instance,
        User $user,
        SitePage $sitepage
    ) {
        parent::__construct($instance, $user, $sitepage);
        $this->setFolderMapper(FolderMapper::getInstance());
        $this->setBlogMapper(BlogMapper::getInstance());
        $this->setBlogImageMapper(BlogImageMapper::getInstance());
        $this->setBlogCommentMapper(BlogCommentMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
    }

    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
        return true;
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        // Work out which folder we are in and grab a handle to it

        try {
            /** @var FolderModel $folder */
            $folder = $this->getFolderMapper()->findById($this->getInstance()->getIdentifier());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        // Get all the blog entries for this page, limit to the number specified in blog component config

        $blogs = $this->getBlogMapper()
            ->findByColumn("folder_id", $this->getInstance()->getIdentifier())
            ->sortBy('createdAt', 'desc')
            ->limit(0, $this->getConfig('size'));

        // Grab comments and image gallery items for each blog post

        $blogComments = [];
        $blogImages = [];

        /** @var BlogModel $blog */
        foreach ($blogs as $blog) {
            $user = $this->getUserMapper()->findById($blog->getOwner()->getId());
            $blog->setOwner($user);

            $blogComments[$blog->getId()] = $this->getBlogCommentMapper()->findByColumn('blog_id', $blog->getId());
            $blogImages[$blog->getId()] = $this->getBlogImageMapper()->findByColumn("blog_id", $blog->getId());

            // Hydrate owner of comments

            /** @var BlogCommentModel $comment */
            foreach ($blogComments[$blog->getId()] as $comment) {
                $comment->setOwner($this->getUserMapper()->findById($comment->getOwner()->getId()));
            }
        }

        // Render this component out

        return $this->renderTwigTemplate(
            $templateHandle,
            '@blog/component.twig',
            array(
                'session'               => $_SESSION,
                'folder'                => $folder,
                'blogs'                 => $blogs,
                'blogComments'          => $blogComments,
                'blogImages'            => $blogImages,
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName()
            ),
            $this->hasWriteAccess() || !$this->getConfig('compactMode')
        );

        return false;
    }

    /**
     * Here be the getters/setters, nothing to see here ¯\_(ツ)_/¯
     */

    /**
     * @var FolderMapper;
     */
    protected $folderMapper;

    /**
     * @var BlogMapper;
     */
    protected $blogMapper;

    /**
     * @var ErrorMapper;
     */
    protected $errorMapper;

    /**
     * @var UserMapper;
     */
    protected $userMapper;

    /**
     * @var BlogCommentMapper;
     */
    protected $blogCommentMapper;

    /**
     * @var BlogImageMapper;
     */
    protected $blogImageMapper;

    /**
     * @return FolderMapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param  FolderMapper $folderMapper
     * @return $this
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }

    /**
     * @return BlogMapper
     */
    public function getBlogMapper()
    {
        return $this->blogMapper;
    }

    /**
     * @param  BlogMapper $blogMapper
     * @return $this
     */
    public function setBlogMapper($blogMapper)
    {
        $this->blogMapper = $blogMapper;
        return $this;
    }

    /**
     * @return ErrorMapper
     */
    public function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param  ErrorMapper $errorMapper
     * @return $this
     */
    public function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param  UserMapper $userMapper
     * @return $this
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * @return BlogCommentMapper
     */
    public function getBlogCommentMapper()
    {
        return $this->blogCommentMapper;
    }

    /**
     * @param  BlogCommentMapper $blogCommentMapper
     * @return $this
     */
    public function setBlogCommentMapper($blogCommentMapper)
    {
        $this->blogCommentMapper = $blogCommentMapper;
        return $this;
    }

    /**
     * @return BlogImageMapper
     */
    public function getBlogImageMapper()
    {
        return $this->blogImageMapper;
    }

    /**
     * @param  BlogImageMapper $blogImageMapper
     * @return $this
     */
    public function setBlogImageMapper($blogImageMapper)
    {
        $this->blogImageMapper = $blogImageMapper;
        return $this;
    }
}
