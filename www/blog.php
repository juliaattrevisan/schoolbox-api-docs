<?php

$groupHandler = new Group();
$folderMapper = new Schoolbox\Resource\Folder\Mapper();
$userMapper = new \Schoolbox\UserMapper();
$blogMapper = new \Schoolbox\Blog\BlogMapper();
$commentMapper = new \Schoolbox\Blog\CommentMapper();

$this->setFile(TEMPLATE_content, 'blog.html');
$this->setBlock(TEMPLATE_content, 'modify_link', 'modify_link_block');
$this->setBlock(TEMPLATE_content, 'comment', 'comment_block');
$this->setBlock('comment', 'comment_delete_link', 'comment_delete_link_block');

if (is_numeric($this->arg[0])) {
    /** @var \Schoolbox\Blog\Blog $blog */
    try {
        $blog = $blogMapper->findById($this->arg[0]);
        $blog->setCategory($folderMapper->findById($blog->getCategory()->getId()));
        // Load owner too
        try {
            $blog->setOwner($userMapper->findById($blog->getOwner()->getId()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            // Unknown user is fine - noop
        }
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(29);
    }
    $permission = $groupHandler->getPermission($blog->getCategory()->getId());

    $userHandler = new User();

    $this->setVar(
        array(
            'BLOG_CID' => $blog->getCategory()->getId(),
            'BLOG_HEADING' => Scrub::text($blog->getHeading()),
            'BLOG_BODY' => Scrub::html($blog->getBody()),
            'BLOG_COMMENTS' => 'No Comments',
            'BLOG_ID' => $blog->getId(),
            'BLOG_CREATED' => $blog->getCreated()->getRelativeTime(),
            'BLOG_AUTHOR_ID' => $blog->getOwner()->getId(),
            'BLOG_AUTHOR_EXTERNAL_ID' => $blog->getOwner()->getExternalId(),
            'BLOG_AUTHOR_NAME' => $blog->getOwner()->formatName(),
            'modify_link_block' => '',
            'comment_block'     => '',
        )
    );
    if ($blog->getOwner()->getId() == $_SESSION['id'] || $permission >= FULL_ACCESS) {
        $this->parse('modify_link_block', 'modify_link');
    }

    /** @var \Schoolbox\Blog\Comment[] $comments */
    $comments = $commentMapper->findForBlog($blog);

    if (!empty($comments)) {
        $commentCount = count($comments);
        $this->setVar('BLOG_COMMENTS', $commentCount . ($commentCount > 1 ? ' Comments' : ' Comment'));
        foreach ($comments as $comment) {
            $this->setVar(
                array(
                    'COMMENT_ID' => $comment->getId(),
                    'COMMENT_BODY' => Scrub::text($comment->getBody()),
                    'COMMENT_OWNER' => $comment->getOwner()->formatName(),
                    'COMMENT_CREATED' => $comment->getCreated()->getRelativeTime(),
                    'COMMENT_AUTHOR_ID' => $comment->getOwner()->getId(),
                    'COMMENT_AUTHOR_EXTERNAL_ID' => $comment->getOwner()->getExternalId(),
                    'comment_delete_link_block' => '',
                )
            );

            if ($comment->getOwner()->getId() == $_SESSION['id'] || $permission >= FULL_ACCESS) {
                $this->parse('comment_delete_link_block', 'comment_delete_link');
            }

            $this->parse('comment_block', 'comment', true);
        }
    }

    $trail = new \Schoolbox\Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $blog->getCategory()->getName(),
            'href' => "/homepage/{$blog->getCategory()->getId()}",
            'class' => '',
        ],
        [
            'text' => $blog->getHeading(),
            'href' => '',
            'class' => 'active',
        ]
    ]);
}
