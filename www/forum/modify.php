<?php

use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Forum\PostMapper;
use Schoolbox\Forum\ThreadMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Breadcrumbs;
use Schoolbox\Permission\Access\UserAccess;

if (!isset($this)) {
    http_response_code(404);
    die('No direct access permitted');
}
/** @var SitePage $this */

$threadMapper = ThreadMapper::getInstance();
$postMapper = PostMapper::getInstance();
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
        $folder = FolderMapper::getInstance()->findById($this->arg[0]);
    } catch (Exception $e) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
    }

    /* Forum exists
     */
    if (!$threadMapper->isComponent($folder->getId())) {
        SitePage::localRedir('/homepage/' . $folder->getId());
    }

    /* Check permissions
     */
    $access      = Access::getInstance();
    $accessLevel = $access->getFolderAccess($_SESSION['user'], $folder->getId());
    if ($accessLevel < UserAccess::ACCESS_READ) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }
    if ($groupHandler->hasChildAccessOnly($folder->getId(), $_SESSION['user']->getId())) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
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
    if (is_numeric($this->arg[2] ?? null) && $this->arg[2] > 0) {
        try {
            $post   = $postMapper->findById($this->arg[2]);
            $thread = $threadMapper->findById($post->getThread()->getId());
        } catch (Exception $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        /* Check user owns post
         */
        if ($post->getOwner()->getId() != $_SESSION['id'] && $accessLevel < UserAccess::ACCESS_WRITE) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        }

        /* check locked
         */
        if ($thread->isLocked() && $accessLevel < UserAccess::ACCESS_WRITE) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        }

        // first!
        if ($thread->getFirstPost()->getId() == $post->getId()) {
            $output['THREAD_TITLE'] = Scrub::text($thread->getTitle());
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
    } elseif (is_numeric($this->arg[1] ?? null)) {
        /* Add new reply
         */
        try {
            $thread = $threadMapper->findById($this->arg[1]);
        } catch (Exception $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        /* Check locked
         */
        if ($accessLevel < UserAccess::ACCESS_WRITE && $thread->isLocked()) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        }

        /* Only inject a blockquote when creating a new reply
         */
        $quotePostID = $this->arg[3] ?? null;

        if (!is_numeric($quotePostID)) {
            //Gets the oldest post to use as a thread reply
            $quotePostID = $thread->getFirstPost()->getId();
        }

        if (is_numeric($quotePostID) && $quotePostID > 0) {
            try {
                $quote = $postMapper->findById($quotePostID);

                $this->setVar(array(
                        'QUOTE_ID' => $quotePostID,
                        'QUOTE_BODY' => Scrub::html($quote->getBody()),
                        'QUOTE_AUTHOR' => Scrub::text($quote->getOwner()->formatName()),
                        'QUOTE_AUTHOR_ID' => $quote->getOwner()->getId()
                    ));
                $this->parse('quote_block', 'quote');
            } catch (Exception $e) {
            }
        }

        $output['QUOTE_ID'] = $quotePostID;
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
    } else {
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
} else {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
}
