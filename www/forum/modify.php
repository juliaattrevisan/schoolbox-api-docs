<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

use Schoolbox\Forum\PostMapper;
use Schoolbox\Forum\ThreadMapper;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Breadcrumbs;

$forumHandler = new Forum();
$groupHandler = new Group();

$this->setFile(TEMPLATE_content, 'forum/modify.html');
$this->setBlock(TEMPLATE_content, 'title', 'title_block');
$this->setBlock(TEMPLATE_content, 'quote', 'quote_block');

$this->setVar(array(
    'title_block' => '',
    'quote_block' => '',
));

if (is_numeric($this->arg[0])) {
    /* Category exists
     */
    try {
        /** @var \Schoolbox\Resource\Category $folder */
        $folder = CategoryMapper::getInstance()->findById($this->arg[0]);
    } catch (Exception $e) {
        error(2);
    }

    /* Forum exists
     */
    if (!$forumHandler->hasForum($folder->getId())) {
        SitePage::localRedir('/homepage/' . $folder->getId());
    }

    /* Check permissions
     */
    if ($groupHandler->getPermission($folder->getId()) < READ_ACCESS) {
        error(19);
    }
    if ($groupHandler->hasChildAccessOnly($folder->getId())) {
        error(19);
    }

    $this->setTitle(Scrub::text($folder->getName() . ' Forum'));

    $output = array(
        'THREAD_ID'    => '',
        'THREAD_TITLE' => '',
        'POST_ID'      => '',
        'QUOTE_ID'     => '',
        'CATEGORY_ID'  => $folder->getId(),
        'CATEGORY'     => Scrub::text($folder->getName()),
        'BODY'         => '',
    );

    /* Edit post
     */
    if (is_numeric($this->arg[2]) && $this->arg[2] > 0) {
        try {
            /** @var \Schoolbox\Forum\Post $post */
            $post   = PostMapper::getInstance()->findById($this->arg[2]);

            /** @var \Schoolbox\Forum\Thread $thread */
            $thread = ThreadMapper::getInstance()->findById($post->getThread()->getId());
        }
        catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            //There is cases where you need to edit deleted stuff so letting this go through..
        }
        catch (Exception $e) {
            error(2);
        }

        /* Check user owns post
         */
        if ($post->getOwner()->getId() != $_SESSION['id']
            && $groupHandler->getPermission($folder->getId()) < FULL_ACCESS
        ) {
            error(19);
        }

        /* check locked
         */
        if ($thread->isLocked() && $groupHandler->getPermission($folder->getId()) < FULL_ACCESS) {
            error(19);
        }

        // first!
        if ($thread->getFirstPost()->getId() == $post->getId()) {
            $output['THREAD_TITLE'] = $thread->getTitle();
            $this->parse('title_block', 'title');
        }

        $output['ACTION']       = 'Modify';
        $output['ACTION_CLASS'] = 'icon-modify';
        $output['THREAD_POST']  = 'Post';
        $output['THREAD_ID']    = $thread->getId();
        $output['POST_ID']      = $post->getId();
        $output['BODY']         = Scrub::text($post->getBody());

        /* Render out breadcrumbs
         */
        $trail = new Breadcrumbs();
        $trail->renderIntoSitePage($this, 'BREADCRUMB', [
            [
                'text' => $folder->getName(),
                'href' => "/homepage/{$folder->getId()}",
                'class' => '',
            ],
            [
                'text' => 'Forum',
                'href' => "/forum/{$folder->getId()}",
                'class' => '',
            ],
            [
                'text' => 'Modify Post',
                'href' => "/forum/modify/{$folder->getId()}/{$post->getId()}/{$thread->getId()}",
                'class' => 'active',
            ]
        ]);
    }
    elseif (is_numeric($this->arg[1])) {

        /* Add new reply
         */
        try {
            /** @var \Schoolbox\Forum\Thread $thread */
            $thread = ThreadMapper::getInstance()->findById($this->arg[1]);
        }
        catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            //There is cases where you need to edit deleted stuff so letting this go through..
        }
        catch (Exception $e) {
            error(2);
        }

        /* Check locked
         */
        if ($thread->isLocked() && $groupHandler->getPermission($folder->getId()) < FULL_ACCESS) {
            error(19);
        }

        /* Only inject a blockquote when creating a new reply
         */
        $quotePostID = $this->arg[3];

        if (!is_numeric($quotePostID) && $this->arg[1]) {
            //Gets the oldest post to use as a thread reply
            $quotePostID = $thread->getFirstPost()->getId();
        }

        if (is_numeric($quotePostID) && $quotePostID > 0) {
            try {
                $quote = PostMapper::getInstance()->findById($quotePostID);

                $this->setVar(array(
                        'QUOTE_ID' => $quotePostID,
                        'QUOTE_BODY' => Scrub::html($quote->getBody()),
                        'QUOTE_AUTHOR' => $quote->getOwner()->formatName(),
                        'QUOTE_AUTHOR_ID' => $quote->getOwner()->getId()
                    ));
                $this->parse('quote_block', 'quote');
            }
            catch (Exception $e) {
            }
        }

        $output['ACTION']       = 'Post';
        $output['ACTION_CLASS'] = 'icon-add';
        $output['THREAD_POST']  = 'Reply';
        $output['THREAD_ID']    = $thread->getId();
        $output['THREAD_TITLE'] = Scrub::text($thread->getTitle());

        /* Render out breadcrumbs
         */
        $trail = new Breadcrumbs();
        $trail->renderIntoSitePage($this, 'BREADCRUMB', [
            [
                'text' => $folder->getName(),
                'href' => "/homepage/{$folder->getId()}",
                'class' => '',
            ],
            [
                'text' => 'Forum',
                'href' => "/forum/{$folder->getId()}",
                'class' => '',
            ],
            [
                'text' => 'Post Reply',
                'href' => "/forum/modify/{$folder->getId()}/{$thread->getId()}",
                'class' => 'active',
            ]
        ]);
    }
    else {
        $output['ACTION']       = 'Post';
        $output['ACTION_CLASS'] = 'icon-add';
        $output['THREAD_POST']  = 'Thread';

        $this->parse('title_block', 'title');

        /* Render out breadcrumbs
         */
        $trail = new Breadcrumbs();
        $trail->renderIntoSitePage($this, 'BREADCRUMB', [
            [
                'text' => $folder->getName(),
                'href' => "/homepage/{$folder->getId()}",
                'class' => '',
            ],
            [
                'text' => 'Forum',
                'href' => "/forum/{$folder->getId()}",
                'class' => '',
            ],
            [
                'text' => 'New Thread',
                'href' => '',
                'class' => 'active',
            ]
        ]);
    }

    $this->setVar($output);
}
else {
    error(2);
}
