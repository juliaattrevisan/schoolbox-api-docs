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
     * @var Blog
     */
    private $blogHandler;

    /**
     * @var Comment
     */
    private $commentHandler;

    /**
     * @var array
     */
    private $source;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        $this->groupHandler = new Group();
        $this->catHandler = new Category();
        $this->blogHandler = new Blog();
        $this->commentHandler = new Comment();

        // remove any non-numeric values from the source config
        $this->source = $this->catHandler->getByID(
            $this->getConfig('src') ?
                preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')) :
                $this->getContext('id')
        );

	    if($this->source === false) {
		    $this->source = $this->catHandler->getByID($this->getContext('id'));
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

		if ($this->groupHandler->getPermission($this->source['id']) < READ_ACCESS) {
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
            'COMPONENT_CONFIG_SRC_ID'   => $this->source['id'],
            'COMPONENT_CONFIG_SRC_NAME' => $this->source['id'] == $this->getContext('id') ? 'Local' : $this->source['name'],

            'BLOG_CATEGORY_ID' => $this->source['id'],
        ));

        $this->blogHandler->config(array('size' => $this->getConfig('size')));
        $blogs = $this->blogHandler->getByCID($this->source['id']);

        /* Have update form to back to this homepage, not the wiki's homepage
         */
        $this->setVar(array(
            'BLOG_CATEGORY_ID' => $this->source['id']
        ));

        /* Controls such as Post News
         */
        if ($this->permission >= WRITE_ACCESS) {
            $this->parse('create_text_block', 'create_text');
        }

        /* Iterate
         */
        if(!empty($blogs)) {
            $userHandler = new User();
            $i = 0;

            foreach($blogs as $blog) {
                if($blog['owner'] == $_SESSION['id'] || $this->permission >= WRITE_ACCESS)
                    $this->setVar("MODIFY_BLOG", '<a class="btn no-text modify" href="/blog/modify/'.$blog['id'].'" title="Modify Blog post"><span></span></a>');
                else
                    $this->setVar('MODIFY_BLOG', '');

                $blog['name'] = $userHandler->formatName($blog);
                $createdTime = new Alaress_Date_Relative($blog['created']);

                $user = $userHandler->getByID($blog['owner']);
                $synID = $user['synergy_id'];

                $this->setVar(array(
                    'BLOG_ID'		=> $blog['id'],
                    'BLOG_HEADING'  => Scrub::text($blog['heading']),
                    'BLOG_BODY'     => Scrub::html($blog['body']),
                    'BLOG_AUTHOR_ID' => $blog['owner'],
                    'BLOG_AUTHOR_NAME' => $blog['name'],
                    'BLOG_AUTHOR_THUMB' => "/portrait.php?userId=$synID&size=square32",
                    'BLOG_CREATED'  => $createdTime->getRelativeTime(),
                    'BLOG_SHADING'  => ++$i % 2 ? '' : 'dark',
                    'BLOG_COMMENTS' => '',
                    'BLOG_AUTHOR_EXTERNAL_ID' => $blog['synergy_id'],
                ));

                // comments
                $this->setVar('comment_block','');

                $comments = $this->commentHandler->getByBID($blog['id']);
                $totalComments = $this->commentHandler->count($blog['id']);

                $this->setVar('BLOG_TOTAL_COMMENTS', $totalComments);
                $j = 0;

                if (!empty($comments)) {
                    foreach($comments as $comment) {
                        $createdTime = new Alaress_Date_Relative($comment['created']);

                        $comment['name'] = $userHandler->formatName($comment);
                        $this->setVar(array(
							'COMMENT_COMMENT_ID' => $comment['id'],
							'COMMENT_BODY' => Scrub::text($comment['body']),
							'COMMENT_AUTHOR_ID' => $comment['owner'],
							'COMMENT_AUTHOR_NAME' => $comment['name'],
							'COMMENT_CREATED' => $createdTime->getRelativeTime(),
							'COMMENT_AUTHOR_EXTERNAL_ID' => $comment['synergy_id'],
							'ON_OFF' => ++$j % 2 ? 'dark' : ''
                        ));

                        if ($comment['owner'] == $_SESSION['id'] || $this->permission >= WRITE_ACCESS) {
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

}
