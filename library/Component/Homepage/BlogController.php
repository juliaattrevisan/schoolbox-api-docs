<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_BlogController extends Component_Homepage_ControllerAbstract {

    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * @var Category
     */
    private $catHandler;

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
     * User Mapper
     *
     * @var null
     */
    private $userMapper = null;

    /**
     * Category Mapper
     *
     * @var null
     */
    private $categoryMapper = null;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        $this->groupHandler = new Group();

        $this->setBlogMapper(new \Schoolbox\Blog\BlogMapper());
        $this->setCommentMapper(new \Schoolbox\Blog\CommentMapper());
        $this->setUserMapper(new \Schoolbox\UserMapper());
        $this->setCategoryMapper(new \Schoolbox\Resource\CategoryMapper());

        // remove any non-numeric values from the source config
        $sourceId = $this->getConfig('src') ? preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')) : $this->getContext('id');

        try {
            $this->source = $this->getCategoryMapper()->findById($sourceId);
        }
        catch (\Exception $e) {
            $this->source = $this->getCategoryMapper()->findById($this->getContext('id'));
        }
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'blog')) {
            return false;
        }

        $this->parseConfig();

		if ($this->groupHandler->getPermission($this->source->getId()) < READ_ACCESS) {
			return false;
		}

        /* Set up blocks and stuff
         */
        $this->setBlock($templateHandle, 'blog', 'blog_block');
        $this->setBlock($templateHandle, 'create_text', 'create_text_block');
        $this->setBlock('blog', 'has_comments', 'has_comments_block');
        $this->setBlock('has_comments', 'comment', 'comment_block');
		$this->setBlock('comment', 'has_comment_delete', 'has_comment_delete_block');

        $this->setVar(array(
            'create_text_block' => '',
            'has_comments_block' => '',
            'comment_block' => '',
            'has_comment_delete_block' => '',
            'blog_block' => '',
        ));

        /* Set up component name
         */
        $this->setVar(array(
            'COMPONENT_NAME' => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'Blog',

            // These are for the Select Alternate Source resourcebrowser
            'COMPONENT_CONFIG_SRC_ID'   => $this->source->getId(),
            'COMPONENT_CONFIG_SRC_NAME' => $this->source->getId() == $this->getContext('id') ? 'Local' : $this->source->getName(),

            'BLOG_CATEGORY_ID' => $this->source->getId(),
        ));

        $allItems = $this->getBlogMapper()->findByColumn('cid', $this->source->getId());

        /* Have update form to back to this homepage, not the wiki's homepage
         */
        $this->setVar(array(
            'BLOG_CATEGORY_ID' => $this->source->getId(),
        ));

        /* Controls such as Post News
         */
        if ($this->permission >= WRITE_ACCESS) {
            $this->parse('create_text_block', 'create_text');
        }

        /* Iterate
         */
        if ($allItems->count()) {
            $i = 0;

            foreach($allItems as $currentItem) {
                if ($currentItem->getOwner()->getId() == $_SESSION['id'] || $this->permission >= WRITE_ACCESS) {
                    $this->setVar("MODIFY_BLOG", '<a class="btn no-text modify" href="/blog/modify/' . $currentItem->getId() . '" title="Modify Blog post"><span></span></a>');
                }
                else {
                    $this->setVar('MODIFY_BLOG', '');
                }

                $createdTime = new Alaress_Date_Relative($currentItem->getCreated()->getDatabase());
                $owner = $this->getUserMapper()->findById($currentItem->getOwner()->getId());

                $this->setVar(
                    array(
                        'BLOG_ID' => $currentItem->getId(),
                        'BLOG_HEADING' => Scrub::text($currentItem->getHeading()),
                        'BLOG_BODY' => Scrub::html($currentItem->getBody()),
                        'BLOG_AUTHOR_ID' => $owner->getId(),
                        'BLOG_AUTHOR_EXTERNAL_ID' => $owner->getExternalId(),
                        'BLOG_AUTHOR_NAME' => $owner->formatName(),
                        'BLOG_AUTHOR_THUMB' => "/portrait.php?userId={$owner->getExternalId()}&size=square32",
                        'BLOG_CREATED' => $createdTime->getRelativeTime(),
                        'BLOG_SHADING' => ++$i % 2 ? '' : 'dark',
                        'BLOG_COMMENTS' => '',
                    )
                );

                $allComments = $this->getCommentMapper()->findByColumn('bid', $currentItem->getId());

                $this->setVar('BLOG_TOTAL_COMMENTS', $allComments->count());
                $j = 0;

                if ($allComments->count()) {
                    foreach($allComments as $currentComment) {
                        $createdTime = new Alaress_Date_Relative($currentComment->getCreated()->getDatabase());
                        $commentOwner = $this->getUserMapper()->findById($currentComment->getOwner()->getId());

                        $this->setVar(array(
							'COMMENT_COMMENT_ID' => $currentComment->getId(),
							'COMMENT_BODY' => Scrub::text($currentComment->getBody()),
							'COMMENT_AUTHOR_ID' => $commentOwner->getId(),
							'COMMENT_AUTHOR_NAME' => $commentOwner->formatName(),
							'COMMENT_CREATED' => $createdTime->getRelativeTime(),
							'COMMENT_AUTHOR_EXTERNAL_ID' => $commentOwner->getExternalId(),
							'ON_OFF' => ++$j % 2 ? 'dark' : ''
                        ));

                        if ($currentComment->getOwner()->getId() == $_SESSION['id'] || $this->permission >= WRITE_ACCESS) {
                            $this->parse('has_comment_delete_block', 'has_comment_delete');
						}
                        else {
							$this->setVar(array(
								'has_comment_delete_block' => ''
							));
						}

                        $this->parse('comment_block', 'comment', true);

						/* Clear children after parsing out the parent
						 */
						$this->setVar(array(
							'has_comment_delete_block' => ''
						));
                    }

                    $this->parse('has_comments_block', 'has_comments');

                    /* Clear children after parsing out the parent
                     */
                    $this->setVar(array(
                        'comment_block' => '',
                        'has_comment_delete_block' => '',
                    ));
                }
                else {
                    $this->setVar('has_comments_block', '');
                }

                $this->parse('blog_block', 'blog', true);
            }
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
     * Set user mapper
     *
     * @param null $userMapper
     */
    private function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
    }

    /**
     * Get user mapper
     *
     * @return null
     */
    private function getUserMapper()
    {
        return $this->userMapper;
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
