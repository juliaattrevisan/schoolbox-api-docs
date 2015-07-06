<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_BlogController extends Component_Homepage_ControllerAbstract
{

    /**
     * Component name
     * 
     * @var string
     */
    protected $componentName = 'Blog';

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var array
     */
    private $source;

    /**
     * Blog Mapper
     *
     * @var null
     */
    private $blogMapper = null;

    /**
     * Comment Mapper
     *
     * @var null
     */
    private $commentMapper = null;

    /**
     * Category Mapper
     *
     * @var \Schoolbox\Resource\CategoryMapper
     */
    private $categoryMapper = null;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
        $this->groupHandler = new Group();

        $this->setBlogMapper(new \Schoolbox\Blog\BlogMapper());
        $this->setCommentMapper(new \Schoolbox\Blog\CommentMapper());
        $this->setCategoryMapper(new \Schoolbox\Resource\CategoryMapper());

        $this->source = $this->getContext();

        if (is_numeric($this->getConfig('src'))) {
            try {
                // remove any non-numeric values from the source config
                $this->source = $this->getCategoryMapper()->findById(preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')));
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'blog')) {
            return false;
        }

        $this->parseConfig();

        if ($this->permission < READ_ACCESS) {
            return false;
        }

        /* Set up blocks and stuff
         */
        $this->setBlock($templateHandle, 'blog', 'blog_block');
        $this->setBlock($templateHandle, 'create_blog', 'create_blog_block');
        $this->setBlock('blog', 'modify_blog', 'modify_blog_block');
        $this->setBlock('blog', 'has_comments', 'has_comments_block');
        $this->setBlock('has_comments', 'comment', 'comment_block');
        $this->setBlock('comment', 'has_comment_delete', 'has_comment_delete_block');

        $this->setVar(
            array(
                'modify_blog_block'        => '',
                'create_blog_block'        => '',
                'has_comments_block'       => '',
                'comment_block'            => '',
                'has_comment_delete_block' => '',
                'blog_block'               => '',
            )
        );

        /* Set up component name
         */
        $this->setVar(
            array(
                'COMPONENT_NAME'            => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig(
                        'componentname'
                    ) : 'Blog',
                // These are for the Select Alternate Source resourcebrowser
                'COMPONENT_CONFIG_SRC_ID'   => $this->source->getId(),
                'COMPONENT_CONFIG_SRC_NAME' => $this->source->getId() == $this->getContext()->getId() ? 'Local' : $this->source->getName(),
                /* Have update form to back to this homepage, not the wiki's homepage
                 */
                'BLOG_CATEGORY_ID'          => $this->source->getId(),
            )
        );

        $allItems = $this->getBlogMapper()->findForCategory($this->source, $this->getConfig('size'));

        /* Iterate
         */
        if ($allItems->count()) {
            $i = 0;

            foreach ($allItems as $currentItem) {
                $this->setVar(
                    array(
                        'BLOG_ID'                 => $currentItem->getId(),
                        'BLOG_HEADING'            => Scrub::text($currentItem->getHeading()),
                        'BLOG_BODY'               => Scrub::html($currentItem->getBody()),
                        'BLOG_AUTHOR_ID'          => $currentItem->getOwner()->getId(),
                        'BLOG_AUTHOR_EXTERNAL_ID' => $currentItem->getOwner()->getExternalId(),
                        'BLOG_AUTHOR_NAME'        => $currentItem->getOwner()->formatName(),
                        'BLOG_AUTHOR_THUMB'       => "/portrait.php?userId={$currentItem->getOwner()->getExternalId()}&size=square32",
                        'BLOG_CREATED'            => $currentItem->getCreated()->getRelativeTime(),
                        'BLOG_SHADING'            => ++$i % 2 ? '' : 'dark',
                        'BLOG_COMMENTS'           => '',
                    )
                );

                $allComments = $this->getCommentMapper()->findForBlog($currentItem);

                if ($allComments->count()) {
                    $j = 0;

                    foreach ($allComments as $currentComment) {
                        $this->setVar(
                            array(
                                'COMMENT_COMMENT_ID'         => $currentComment->getId(),
                                'COMMENT_BODY'               => nl2br(Scrub::text($currentComment->getBody())),
                                'COMMENT_AUTHOR_ID'          => $currentComment->getOwner()->getId(),
                                'COMMENT_AUTHOR_NAME'        => $currentComment->getOwner()->formatName(),
                                'COMMENT_CREATED'            => $currentComment->getCreated()->getRelativeTime(),
                                'COMMENT_AUTHOR_EXTERNAL_ID' => $currentComment->getOwner()->getExternalId(),
                                'ON_OFF'                     => ++$j % 2 ? 'dark' : ''
                            )
                        );

                        if ($currentComment->getOwner()->getId() == $_SESSION['id'] || $this->permission >= WRITE_ACCESS
                        ) {
                            $this->parse('has_comment_delete_block', 'has_comment_delete');
                        } else {
                            $this->setVar(
                                array(
                                    'has_comment_delete_block' => ''
                                )
                            );
                        }

                        $this->parse('comment_block', 'comment', true);

                        /* Clear children after parsing out the parent
                         */
                        $this->setVar(
                            array(
                                'has_comment_delete_block' => ''
                            )
                        );
                    }

                    $this->setVar(
                        array(
                            'BLOG_TOTAL_COMMENTS' => $allComments->count(),
                        )
                    );

                    $this->parse('has_comments_block', 'has_comments');

                    /* Clear children after parsing out the parent
                     */
                    $this->setVar(
                        array(
                            'comment_block'            => '',
                            'has_comment_delete_block' => '',
                        )
                    );
                } else {
                    $this->setVar(
                        array(
                            'BLOG_TOTAL_COMMENTS' => $allComments->count(),
                        )
                    );
                }

                /* Control for modifying a blog
                 */
                if ($currentItem->getOwner()->getId() == $_SESSION['id'] || $this->permission >= WRITE_ACCESS) {
                    $this->parse('modify_blog_block', 'modify_blog');
                } else {
                    $this->setVar(
                        array(
                            'modify_blog_block' => ''
                        )
                    );
                }

                $this->parse('blog_block', 'blog', true);

                /* Clear children after parsing out the parent
                 */
                $this->setVar(
                    array(
                        'modify_blog_block'  => '',
                        'has_comments_block' => '',
                        'comment_block'      => ''
                    )
                );
            }
        }

        /* Controls such as Post Blog
         */
        if ($this->permission >= WRITE_ACCESS) {
            $this->parse('create_blog_block', 'create_blog');
        }

        return true;
    }

    /* Getters/Setters
     */

    /**
     * Set blog mapper
     *
     * @param null $blogMapper
     */
    private function setBlogMapper($blogMapper)
    {
        $this->blogMapper = $blogMapper;
    }

    /**
     * Get blog mapper
     *
     * @return null
     */
    private function getBlogMapper()
    {
        return $this->blogMapper;
    }

    /**
     * Set comment mapper
     *
     * @param null $commentMapper
     */
    private function setCommentMapper($commentMapper)
    {
        $this->commentMapper = $commentMapper;
    }

    /**
     * Get comment mapper
     *
     * @return null
     */
    private function getCommentMapper()
    {
        return $this->commentMapper;
    }

    /**
     * Set category mapper
     *
     * @param null $categoryMapper
     */
    private function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
    }

    /**
     * Get category mapper
     *
     * @return null
     */
    private function getCategoryMapper()
    {
        return $this->categoryMapper;
    }
}
