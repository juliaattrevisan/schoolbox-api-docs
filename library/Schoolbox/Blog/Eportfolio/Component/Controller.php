<?php
/**
 * Normal (non-eportfolio) blog component controller. Is responsible for rendering the TWIG into component 
 * on non-eportfolio pages
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use SitePage;

class Controller extends Component_Eportfolio_ControllerAbstract
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
        $this->setEportfolioBlogMapper(EportfolioBlogMapper::getInstance());
        $this->setEportfolioBlogImageMapper(EportfolioBlogImageMapper::getInstance());
        $this->setEportfolioBlogCommentMapper(EportfolioBlogCommentMapper::getInstance());
        $this->setEportfolioPageMapper(EportfolioPageMapper::getInstance());
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
        foreach ($blogs as $blog)
        {
            $user = $this->getUserMapper()->findById($blog->getOwner()->getId());
            $blog->setOwner($user);

            $blogComments[$blog->getId()] = $this->getEportfolioBlogCommentMapper()->findByColumn('blog_id', $blog->getId());
            $blogImages[$blog->getId()] = $this->getEportfolioBlogImageMapper()->findByColumn("blog_id", $blog->getId());

            // Hydrate owner of comments
            
            /** @var EportfolioBlogCommentModel $comment */
            foreach ($blogComments[$blog->getId()] as $comment) {
                $comment->setOwner($this->getUserMapper()->findById($comment->getOwner()->getId()));
            }
        }
        
        // Render this component out

        return $this->renderTwigTemplate(
            $templateHandle,
            '@eportfolioBlog/component.twig',
            array(
                'session'               => $_SESSION,
                'blogs'                 => $blogs,
                'blogComments'          => $blogComments,
                'blogImages'            => $blogImages,
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName(),
                'page'                  => $page
            ),
            $this->hasWriteAccess() || !$this->getConfig('compactMode')
        );
    }

    /**
     * Here be the getters/setters, nothing to see here ¯\_(ツ)_/¯
     */

    /**
     * @var FolderMapper;
     */
    protected $folderMapper;

    /**
     * @var EportfolioBlogMapper;
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
