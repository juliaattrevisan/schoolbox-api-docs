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

        $this->source = $this->catHandler->getByID(
            $this->getConfig('src') ?
                $this->getConfig('src') :
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




        $this->setBlock($templateHandle, 'blog', 'blog_block');
        $this->setBlock($templateHandle, 'submit', 'submit_block');
        $this->setBlock('blog', 'comment', 'comment_block');
        $this->setBlock('blog', 'show_all', 'show_all_block');
        $this->setBlock('blog', 'show_comment', 'show_comment_block');

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

        $this->commentHandler->config(array('comments_size' => $this->getConfig('comments_size')));

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
            $j = 0;

            foreach($blogs as $blog) {
                if($blog['owner'] == $_SESSION['id'] || $this->permission >= FULL_ACCESS || (NEWS_teacher_edit_other && $this->permission >= WRITE_ACCESS))
                    $this->setVar('MODIFY_BLOG', "<a href=\"/blog/modify/{$blog['id']}\">Modify</a>");
                else
                    $this->setVar('MODIFY_BLOG', '');

                $blog['name'] = $userHandler->formatName($blog);
                $createdTime = new Alaress_Date_Relative($blog['created']);

                $this->setVar(array(
                    'BLOG_ID'		=> $blog['id'],
                    'BLOG_HEADING'  => Scrub::text($blog['heading']),
                    'BLOG_BODY'     => Scrub::html($blog['body']),
                    'BLOG_OWNER'    => "<a href=\"/search/user/{$blog['owner']}\">{$blog['name']}</a>",
                    'BLOG_CREATED'  => $createdTime->getRelativeTime(),
                    'BLOG_SHADING'  => ++$j % 2 ? '' : 'dark',
                    'BLOG_COMMENTS' => ''
                ));

                // comments
                $this->setVar('comment_block','');

                $comments = $this->commentHandler->getByBID($blog['id']);
                $totalComments = $this->commentHandler->count($blog['id']);
                $showingComments = count($comments);

                $this->setVar('BLOG_TOTAL_COMMENTS', $totalComments);
                if($totalComments > $showingComments)
                    $this->parse('show_all_block','show_all');
                else
                    $this->setVar('show_all_block','');

                if(!empty($comments)) {

                    $this->setVar('BLOG_COMMENTS', $showingComments);
                    $this->parse('show_comment_block','show_comment' );

                    foreach($comments as $comment) {
                        $createdTime = new Alaress_Date_Relative($comment['created']);

                        $comment['name'] = $userHandler->formatName($comment);
                        $this->setVar(array(
                            'COMMENT_BODY'    => Scrub::text($comment['body']),
                            'COMMENT_OWNER'   => "<a href=\"/search/user/{$comment['owner']}\">{$comment['name']}</a>",
                            'COMMENT_CREATED' => $createdTime->getRelativeTime()
                        ));

                        if($comment['owner'] == $_SESSION['id'] || $this->permission >= WRITE_ACCESS)
                            $this->setVar('DELETE_COMMENT', "<a class=\"delete-comment\" href=\"/blog/comment/delete/{$comment['id']}\">Delete</a>");
                        else
                            $this->setVar('DELETE_COMMENT', '');

                        $this->parse('comment_block', 'comment', true);
                    }
                } else {
                    $this->setVar('comment_block', '');
                    $this->setVar('show_comment_block', '');
                }
                $this->parse('blog_block', 'blog', true);
            }
        }

        return true;
    }

}
