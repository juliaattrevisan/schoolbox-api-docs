<?php
/**
 * Blog component for homepage
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Blog\HomepageComponent;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use SitePage;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Blog\Blog\Mapper as BlogMapper;
use Schoolbox\Blog\Blog\Model as BlogModel;
use Schoolbox\Blog\Comment\Mapper as BlogCommentMapper;
use Schoolbox\Blog\Comment\Model as BlogCommentModel;
use Schoolbox\Blog\Image\Mapper as BlogImageMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\UserMapper;

class Controller extends Component_Homepage_ControllerAbstract
{
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
     *
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $blogs = $this->getBlogMapper()
            ->findByColumn("folder_id", $this->getInstance()->getIdentifier())
            ->sortBy('createdAt', 'desc')
            ->limit(0, $this->getConfig('size'));

        $blogComments = [];
        $blogImages = [];

        // This creates an array of unique year/months for the date selector

        /** @var BlogModel $blog */
        foreach ($blogs as $blog) {
            $user = $this->getUserMapper()->findById($blog->getOwner()->getId());
            $blog->setOwner($user);

            $blogComments[$blog->getId()] = $this->getBlogCommentMapper()->findByColumn('blog_id', $blog->getId());
            $blogImages[$blog->getId()] = $this->getBlogImageMapper()->findByColumn("blog_id", $blog->getId());

            /** @var BlogCommentModel $comment */
            foreach ($blogComments[$blog->getId()] as $comment) {
                $comment->setOwner($this->getUserMapper()->findById($comment->getOwner()->getId()));
            }
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            '@blog/component.twig',
            array(
                'session'               => $_SESSION,
                'blogs'                 => $blogs,
                'blogComments'          => $blogComments,
                'blogImages'            => $blogImages,
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName()
            ),
            $this->hasWriteAccess() || !$this->getConfig('compactMode')
        );
    }

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
