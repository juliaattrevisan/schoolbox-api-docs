<?php
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

use Schoolbox\Forum\PostMapper;
use Schoolbox\Forum\ThreadMapper;
use Schoolbox\Resource\CategoryMapper;

$forumHandler = new Forum();
$groupHandler = new Group();

$this->setFile(TEMPLATE_content, 'forum/modify.html');
$this->setBlock(TEMPLATE_content, 'thread_link', 'thread_link_block');
$this->setBlock(TEMPLATE_content, 'title', 'title_block');
$this->setBlock(TEMPLATE_content, 'quote', 'quote_block');

$this->setVar('title_block', '');
$this->setVar('quote_block', '');

if (is_numeric($this->arg[0])) {
    /* Category exists
     */
    try {
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
        'CATEGORY_ID'  => $folder->getId(),
        'CATEGORY'     => Scrub::text($folder->getName()),
        'BODY'         => '',
    );

    /* Edit post
     */
    if (is_numeric($this->arg[2]) && $this->arg[2] > 0) {
        try {
            $post   = PostMapper::getInstance()->findById($this->arg[2]);
            $thread = ThreadMapper::getInstance()->findById($post->getThreadId());
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            //There is cases where you need to edit deleted stuff so letting this go through..
        } catch (Exception $e) {
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

        if ($thread->getFirstPost()->getPostId() == $post->getPostId()) {
            $output['THREAD_TITLE'] = $thread->getTitle();
            $this->parse('title_block', 'title');
        }

        $output['ACTION']      = 'Modify';
        $output['THREAD_POST'] = 'Post';
        $output['THREAD_ID']   = $thread->getThreadId();
        $output['POST_ID']     = $post->getPostId();
        $output['BODY']        = Scrub::text($post->getBody());


        $this->parse('thread_link_block', 'thread_link');
    } elseif (is_numeric($this->arg[1])) {
        /* Add new reply
         */
        try {
            $thread = ThreadMapper::getInstance()->findById($this->arg[1]);
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            //There is cases where you need to edit deleted stuff so letting this go through..
        } catch (Exception $e) {
            error(2);
        }

        /* Check locked
         */
        if ($thread->isLocked() && $groupHandler->getPermission($folder->getId()) < FULL_ACCESS) {
            error(19);
        }


        $output['ACTION']       = 'Post';
        $output['THREAD_POST']  = 'Reply';
        $output['THREAD_ID']    = $thread->getThreadId();
        $output['THREAD_TITLE'] = Scrub::text($thread->getTitle());


        $this->parse('thread_link_block', 'thread_link');
    } else {
        $output['ACTION']      = 'Post';
        $output['THREAD_POST'] = 'Thread';

        $this->parse('title_block', 'title');
        $this->setVar('thread_link_block', '');
    }


    $this->setVar($output);

    /* output quote
     */
    $this->setVar('quote_block', '');

    $quotePostID = $this->arg[3];

    if (!is_numeric($quotePostID) && $this->arg[1]) {
        //Gets the oldest post to use as a thread reply
        $quotePostID = $thread->getFirstPost()->getPostID();
    }

    if (is_numeric($quotePostID) && $quotePostID > 0) {
        try {
            $quote = PostMapper::getInstance()->findById($quotePostID);
            if (!$output['BODY']) {
                $output['BODY'] =
                    '<blockquote class="mceNonEditable">' . Scrub::html($quote->getBody()) .
                    '<cite>' . $quote->getOwner()->formatName($user) . '</cite>' .
                    '</blockquote><p></p>';
            }

            $this->setVar($output);
            $this->parse('quote_block', 'quote', true);
        } catch (Exception $e) {
        }
    }
} else {
    error(2);
}
