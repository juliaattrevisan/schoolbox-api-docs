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

		if($this->groupHandler->getPermission($this->source['id']) < READ_ACCESS) {
			return false;
		}

        $this->setBlock($templateHandle, 'blog', 'blog_block');
        $this->setBlock($templateHandle, 'submit', 'submit_block');
        $this->setBlock('blog', 'comment', 'comment_block');

        /* Set up component name
         */
        $this->setVar(array(
            'COMPONENT_NAME' => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'Blog',

            // These are for the Select Alternate Source resourcebrowser
            'COMPONENT_CONFIG_SRC_ID'   => $this->source['id'],
            'COMPONENT_CONFIG_SRC_NAME' => $this->source['id'] == $this->getContext('id') ? 'Local' : $this->source['name'],

            'BLOG_CATEGORY_ID' => $this->source['id'],
        ));

        /* Prevent multiple blog entries from other categories stacking up in the wrong place
        * and initialise empty blog roll by default
        */
        $this->setVar('blog_block', '');

        $this->blogHandler->config(array('size' => $this->getConfig('size')));
        $blogs = $this->blogHandler->getByCID($this->source['id']);

        /* Have update form to back to this homepage, not the wiki's homepage
       */
        $this->setVar(array(
            'BLOG_CATEGORY_ID' => $this->source['id']
        ));

        /* Controls such as Post News
       */
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("submit_block", "");
        }
        else {
            $this->parse("submit_block", "submit");
        }


        /* Iterate
        */
        if(!empty($blogs)) {
            $userHandler = new User();
            $i = 0;

            foreach($blogs as $blog) {
                if($blog['owner'] == $_SESSION['id'] || $this->permission >= FULL_ACCESS || (NEWS_teacher_edit_other && $this->permission >= WRITE_ACCESS))
                    $this->setVar("MODIFY_BLOG", '<a class="btn modify" href="/blog/modify/'.$blog['id'].'"><span></span>Modify</a>');
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
                    // 'BLOG_OWNER'    => "<a href=\"/search/user/{}\">{}</a>",
                    'BLOG_AUTHOR_ID' => $blog['owner'],
                    'BLOG_AUTHOR_NAME' => $blog['name'],
                    'BLOG_AUTHOR_THUMB' => "/portrait.php?userId=$synID&size=square32",
                    'BLOG_CREATED'  => $createdTime->getRelativeTime(),
                    'BLOG_SHADING'  => ++$i % 2 ? 'dark' : '',
                    'BLOG_COMMENTS' => '',
                    'BLOG_AUTHOR_EXTERNAL_ID' => $blog['synergy_id'],
                ));

                // comments
                $this->setVar('comment_block','');

                $comments = $this->commentHandler->getByBID($blog['id']);
                $totalComments = $this->commentHandler->count($blog['id']);
                $showingComments = count($comments);

                $this->setVar('BLOG_TOTAL_COMMENTS', $totalComments);
                $j = 0;

                if(!empty($comments)) {
                    foreach($comments as $comment) {
                        $createdTime = new Alaress_Date_Relative($comment['created']);

                        $comment['name'] = $userHandler->formatName($comment);
                        $this->setVar(array(
                            'COMMENT_BODY'    => Scrub::text($comment['body']),
                            'COMMENT_AUTHOR_ID'   => $comment['owner'],
                            'COMMENT_AUTHOR_NAME'   => $comment['name'],
                            'COMMENT_CREATED' => $createdTime->getRelativeTime(),
                            'COMMENT_AUTHOR_EXTERNAL_ID' => $comment['synergy_id'],
                            'ON_OFF'          => ++$j % 2 ? 'dark' : ''
                        ));

                        if($comment['owner'] == $_SESSION['id'] || $this->permission >= WRITE_ACCESS)
                            $this->setVar('DELETE_COMMENT', "<a class=\"delete-comment btn delete no-text\" href=\"/blog/comment/delete/{$comment['id']}\"><span></span></a>");
                        else
                            $this->setVar('DELETE_COMMENT', '');

                        $this->parse('comment_block', 'comment', true);
                    }
                } else {
                    $this->setVar('comment_block', '');
                }
                $this->parse('blog_block', 'blog', true);
            }
        }

        return true;
    }

}
