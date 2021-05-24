<?php

/**
 * Normal (non-eportfolio) blog component controller. Is responsible for rendering the TWIG into component
 * on non-eportfolio pages
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Blog\Eportfolio\Component;

use Component_Eportfolio_ControllerAbstract;
use Component_Instance;
use Eportfolio\PageMapper as EportfolioPageMapper;
use Schoolbox\Blog\Eportfolio\Comment\Mapper as EportfolioBlogCommentMapper;
use Schoolbox\Blog\Eportfolio\Comment\Model as EportfolioBlogCommentModel;
use Schoolbox\Blog\Eportfolio\Image\Mapper as EportfolioBlogImageMapper;
use Schoolbox\Blog\Eportfolio\Post\Mapper as EportfolioBlogMapper;
use Schoolbox\Blog\Eportfolio\Post\Model as EportfolioBlogModel;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Eportfolio_Page_Instance;
use Schoolbox\User;
use Schoolbox\UserMapper;
use SitePage;

class Controller extends Component_Eportfolio_ControllerAbstract
{
    /**
     * @return bool
     */
    public function execute()
    {
        $this->setEportfolioBlogMapper($this->getMapperFactory()->get(EportfolioBlogMapper::class));
        $this->setEportfolioBlogImageMapper($this->getMapperFactory()->get(EportfolioBlogImageMapper::class));
        $this->setEportfolioBlogCommentMapper($this->getMapperFactory()->get(EportfolioBlogCommentMapper::class));
        $this->setEportfolioPageMapper($this->getMapperFactory()->get(EportfolioPageMapper::class));
        $this->setUserMapper($this->getMapperFactory()->get(UserMapper::class));
        return true;
    }

    /**
     * @param String $templateHandle
     *
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        // Work out which page we are on and grab a handle to it

        try {
            $page = $this->getEportfolioPageMapper()->findById($this->getInstance()->getIdentifier());
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        // Get all the blog entries for this page (e-portfolio pages have no limit parameter on their blogs)

        $blogs = $this->getEportfolioBlogMapper()
            ->findByColumn("page_id", $this->getInstance()->getIdentifier())
            ->sortBy('createdAt', 'desc');

        // Grab comments and image gallery items for each blog post

        $blogComments = [];
        $blogImages = [];

        /** @var EportfolioBlogModel $blog */
        foreach ($blogs as $blog) {
            // Rehydrate
            $user = $this->getUserMapper()->findByIdWithFallback($blog->getOwner()->getId());
            $blog->setOwner($user);

            $blogComments[$blog->getId()] = $this->getEportfolioBlogCommentMapper()->findByColumn('blog_id', $blog->getId());
            $blogImages[$blog->getId()] = $this->getEportfolioBlogImageMapper()->findByColumn("blog_id", $blog->getId());

            // Hydrate owner of comments

            /** @var EportfolioBlogCommentModel $comment */
            foreach ($blogComments[$blog->getId()] as $comment) {
                $comment->setOwner($this->getUserMapper()->findByIdWithFallback($comment->getOwner()->getId()));
            }
        }

        // Render this component out

        return $this->renderTwigTemplate(
            $templateHandle,
            '@eportfolioBlog/component.twig',
            array(
                'blogs'                 => $blogs,
                'blogComments'          => $blogComments,
                'blogImages'            => $blogImages,
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName(),
                'page'                  => $page
            )
        );
    }

    /**
     * Here be the getters/setters, nothing to see here ¯\_(ツ)_/¯
     */

    /**
     * @var EportfolioBlogMapper;
     */
    protected $blogMapper;

    /**
     * @var UserMapper;
     */
    protected $userMapper;

    /**
     * @var EportfolioBlogCommentMapper;
     */
    protected $blogCommentMapper;

    /**
     * @var EportfolioBlogImageMapper;
     */
    protected $blogImageMapper;

    /**
     * @var EportfolioPageMapper
     */
    protected $eportfolioPageMapper;

    /**
     * @return EportfolioBlogMapper
     */
    public function getEportfolioBlogMapper()
    {
        return $this->blogMapper;
    }

    /**
     * @param  EportfolioBlogMapper $blogMapper
     * @return $this
     */
    public function setEportfolioBlogMapper($blogMapper)
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
     * @return EportfolioBlogCommentMapper
     */
    public function getEportfolioBlogCommentMapper()
    {
        return $this->blogCommentMapper;
    }

    /**
     * @param  EportfolioBlogCommentMapper $blogCommentMapper
     * @return $this
     */
    public function setEportfolioBlogCommentMapper($blogCommentMapper)
    {
        $this->blogCommentMapper = $blogCommentMapper;
        return $this;
    }

    /**
     * @return EportfolioBlogImageMapper
     */
    public function getEportfolioBlogImageMapper()
    {
        return $this->blogImageMapper;
    }

    /**
     * @param  EportfolioBlogImageMapper $blogImageMapper
     * @return $this
     */
    public function setEportfolioBlogImageMapper($blogImageMapper)
    {
        $this->blogImageMapper = $blogImageMapper;
        return $this;
    }

    /**
     * @return EportfolioPageMapper
     */
    public function getEportfolioPageMapper()
    {
        return $this->eportfolioPageMapper;
    }

    /**
     * @param  EportfolioPageMapper $eportfolioPageMapper
     * @return $this
     */
    public function setEportfolioPageMapper($eportfolioPageMapper)
    {
        $this->eportfolioPageMapper = $eportfolioPageMapper;
        return $this;
    }
}
