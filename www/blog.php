<?php

require_once '../library/group.php';
require_once '../library/blog.php';
require_once '../library/Comment.php';

$groupHandler = new Group();
$blogHandler = new Blog();
$commentHandler = new Comment();

$this->setFile(TEMPLATE_content, 'blog.html');

$i = 0;
if (is_numeric($this->arg[0])) {
    $blog = $blogHandler->getByID($this->arg[0]);
    $permission = $groupHandler->getPermission($blog['cid']);

    if (empty($blog)) {
        error(29);
    }

    if ($blog['owner'] == $_SESSION['id'] || $permission >= FULL_ACCESS || NEWS_teacher_edit_other) {
        $this->setVar(
            'MODIFY_BLOG', "<a class=\"btn modify\" href=\"/blog/modify/{$blog['id']}\"><span></span>Modify</a>"
        );
    } else {
        $this->setVar('MODIFY_BLOG', '');
    }

    $adr = new Alaress_Date_Relative($blog['created']);
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
            'BLOG_AUTHOR_NAME' => $userHandler->formatName($blog),
            'BLOG_AUTHOR_THUMB' => sprintf('/portrait?userId=%s&size=square32', $blog['synergy_id']),
        )
    );

    $comments = $commentHandler->getByBID($blog['id']);
    $this->setBlock(TEMPLATE_content, 'comment', 'comment_block');

    if (!empty($comments)) {
        $this->setVar('BLOG_COMMENTS', count($comments) . ' Comments');


        $j = 0;

        foreach ($comments as $comment) {
            $actionDate = new Alaress_Date_Relative($comment['created']);

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

            if ($comment['owner'] == $_SESSION['id'] || $permission >= FULL_ACCESS || NEWS_teacher_edit_other) {
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

?>
