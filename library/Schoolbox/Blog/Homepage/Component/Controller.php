<?php

/**
 * Homepage blog component controller. Is responsible for rendering the TWIG into component
 * on homepage pages
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Blog\Homepage\Component;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Blog\Homepage\Comment\Mapper as BlogCommentMapper;
use Schoolbox\Blog\Homepage\Comment\Model as BlogCommentModel;
use Schoolbox\Blog\Homepage\Image\Mapper as BlogImageMapper;
use Schoolbox\Blog\Homepage\Post\Mapper as BlogMapper;
use Schoolbox\Blog\Homepage\Post\Model as BlogModel;
use Schoolbox\Column\Base as ColumnBase;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccess;
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
        $this->setBlogMapper(BlogMapper::getInstance());
        $this->setBlogImageMapper(BlogImageMapper::getInstance());
        $this->setBlogCommentMapper(BlogCommentMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        if (!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailableTwig($templateHandle);
        } else {
            // Limit to the number specified in blog component config (0 == 'ALL')
            $limit = 0 == $this->getConfig('size') ? null : $this->getConfig('size');

            // Get specified number of blog entries for this source
            $blogs = $this->getBlogMapper()
                ->findByColumn("folder_id", $this->getSource()->getId())
                ->sortBy('createdAt', 'desc')
                ->limit($limit);

            if (($this->getContentPermission() < UserAccess::ACCESS_WRITE) && !count($blogs)) {
                return false;
            }

            // Grab comments and image gallery items for each blog post

            $blogComments = [];
            $blogImages = [];

            /** @var BlogModel $blog */
            foreach ($blogs as $blog) {
                // Rehydrate
                $blogComments[$blog->getId()] = $this->getBlogCommentMapper()->findByColumn('blog_id', $blog->getId());
                $blogImages[$blog->getId()] = $this->getBlogImageMapper()->findByColumn("blog_id", $blog->getId());

                // Hydrate owner of comments
                /** @var BlogCommentModel $comment */
                foreach ($blogComments[$blog->getId()] as $comment) {
                    $comment->setOwner($this->getUserMapper()->findByIdWithFallback($comment->getOwner()->getId()));
                }
            }
        }

        // Render this component out


        return $this->renderTwigTemplate(
            $templateHandle,
            '@blog/component.twig',
            array(
                'session'               => $_SESSION,
                'folder'                => $this->getSource(),
                'blogs'                 => $blogs,
                'blogComments'          => $blogComments,
                'blogCommentMaxLength'  => ColumnBase::TEXT_MAX_LENGTH,
                'blogImages'            => $blogImages,
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName(),
                'readonly'              => ($this->getContentPermission() <= UserAccess::ACCESS_READ),
            )
        );
    }

    /**
     * Here be the getters/setters, nothing to see here ¯\_(ツ)_/¯
     */

    /**
     * @var BlogMapper;
     */
    protected $blogMapper;

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
