<?php

$groupHandler = new Group();
$blogHandler = new Blog();
$commentHandler = new Comment();

$this->setFile(TEMPLATE_content, 'blog.html');
$this->setBlock(TEMPLATE_content, 'modify_link', 'modify_link_block');

$i = 0;
if (is_numeric($this->arg[0])) {
    $blog = $blogHandler->getByID($this->arg[0]);
    $permission = $groupHandler->getPermission($blog['cid']);

    if (empty($blog)) {
        error(29);
    }

    $adr = new Alaress_Date_Format($blog['created'], 'Y-m-d H:i:s');
    $userHandler = new User();

    $this->setVar(
        array(
            'BLOG_CID' => $blog['cid'],
            'BLOG_HEADING' => Scrub::text($blog['heading']),
            'BLOG_BODY' => Scrub::html($blog['body']),
            'BLOG_COMMENTS' => 'No Comments',
            'BLOG_ID' => $blog['id'],
            'BLOG_CREATED' => $adr->getRelativeTime(),
            'BLOG_AUTHOR_ID' => $blog['owner'],
            'BLOG_AUTHOR_EXTERNAL_ID' => $blog['synergy_id'],
            'BLOG_AUTHOR_NAME' => $userHandler->formatName($blog),
            'modify_link_block' => '',
        )
    );
    if ($blog['owner'] == $_SESSION['id'] || $permission >= FULL_ACCESS) {
        $this->parse('modify_link_block', 'modify_link');
    }

    $comments = $commentHandler->getByBID($blog['id']);
    $this->setBlock(TEMPLATE_content, 'comment', 'comment_block');

    if (!empty($comments)) {
        $this->setVar('BLOG_COMMENTS', count($comments) . ' Comments');


        $j = 0;

        foreach ($comments as $comment) {
            $actionDate = new Alaress_Date_Format($comment['created'], 'Y-m-d H:i:s');

            $this->setVar(
                array(
                    'COMMENT_BODY' => Scrub::text($comment['body']),
                    'COMMENT_OWNER' => $userHandler->formatName($comment),
                    'COMMENT_CREATED' => $actionDate->getRelativeTime(),
                    'COMMENT_AUTHOR_ID' => $comment['owner'],
                    'COMMENT_AUTHOR_EXTERNAL_ID' => $comment['synergy_id'],
                    'DELETE_COMMENT' => "<a class=\"delete-comment btn delete no-text\" href=\"/blog/comment/delete/{$comment['id']}\"><span></span></a>",
                    'ON_OFF' => ++$j % 2 ? 'dark' : ''
                )
            );

            if ($comment['owner'] == $_SESSION['id'] || $permission >= FULL_ACCESS) {
                $this->setVar(
                    'DELETE_COMMENT',
                    "<a class=\"delete-comment btn delete no-text\" href=\"/blog/comment/delete/{$comment['id']}\"><span></span></a>"
                );
            } else {
                $this->setVar('DELETE_COMMENT', '');
            }

            $this->parse('comment_block', 'comment', true);
        }
    } else {
        $this->setVar('comment_block', '');
    }
}
