<?php
$isExportable = $eportfolioHandler->isExportable();

if ($isExportable) {
    // print all comments below each blog item
    $config['comments_size'] = 0;
}

$blogHandle = new Eportfolio_Blog_Item();
$blogHandle->config($config);

$commentHandle = new Eportfolio_Blog_Comment();
$commentHandle->config($config);

$this->setBlock('config', 'comments_size', 'comments_size_block');

for ($i=0; $i<=10; $i++) {
	$this->setVar('COMPONENT_CONFIG_COMMENTS_SIZE_NAME', $i == 0 ? 'All' : $i);
	$this->setVar('COMPONENT_CONFIG_COMMENTS_SIZE_VALUE', $i);
	$this->setVar('COMPONENT_CONFIG_COMMENTS_SIZE_SELECTED', $i == $config['comments_size'] ? ' selected="selected"' : '');
	$this->parse("comments_size_block", "comments_size", true);
}

$this->setBlock($componentHandle, 'blog', 'blog_block');
$this->setBlock('blog', 'comment', 'comment_block');
$this->setBlock('blog', 'show_all', 'show_all_block');
$this->setBlock('blog', 'show_comment', 'show_comment_block');
$this->setBlock('blog', 'post_comment', 'post_comment_block');

$blogs = $blogHandle->getByOwnerID($userID);
$userHandler = new User();

$this->setVar('post_comment_block', '');
$this->setVar('show_comment_block', '');

if (!$isExportable) {
    $this->parse('post_comment_block', 'post_comment');
}

if (!empty($blogs)) {
	foreach ($blogs as $blog) {
        if ($eportfolioHandler->canEdit($userID)) {
            $this->setVar('MODIFY_BLOG', "<a href=\"/eportfolio/blog/modify/{$blog['id']}\">Modify</a>");
        }
        else {
            $this->setVar('MODIFY_BLOG', '');
        }

		$blog['name'] = $userHandler->formatName($blog);

		$createdTime = new Alaress_Date_Relative($blog['created']);

		$this->setVar(array(
			'BLOG_ID'		=> $blog['id'],
			'BLOG_HEADING'  => Scrub::text($blog['heading']),
			'BLOG_BODY'     => Scrub::html($blog['body']),
			'BLOG_OWNER'    => $isExportable ? $blog['name'] : "<a href=\"/search/user/{$blog['owner']}\">{$blog['name']}</a>",
			'BLOG_CREATED'  => $createdTime->getRelativeTime(),
			'BLOG_SHADING'  => ++$j%2 ? '' : 'dark',
			'BLOG_COMMENTS' => ''
		));

		/* Comments
         */
		$this->setVar('comment_block','');

		$comments = $commentHandle->getByBID($blog['id']);
		$totalComments = $commentHandle->count($blog['id']);
		$showingComments = count($comments);

		$this->setVar('BLOG_TOTAL_COMMENTS', $totalComments);

		if (!$isExportable && $totalComments > $showingComments) {
			$this->parse('show_all_block','show_all');
        }
		else {
			$this->setVar('show_all_block','');
        }

		if (!empty($comments)) {
			$this->setVar('BLOG_COMMENTS', $showingComments);

            if (!$isExportable) {
			    $this->parse('show_comment_block','show_comment' );
            }

			foreach ($comments as $comment) {
				$comment['name'] = $userHandler->formatName($comment);

				$createdTime = new Alaress_Date_Relative($comment['created']);

				$this->setVar(array(
					'COMMENT_BODY'    => Scrub::text($comment['body']),
					'COMMENT_OWNER'   => $isExportable ? $comment['name'] : "<a href=\"/search/user/{$comment['owner']}\">{$comment['name']}</a>",
                    'COMMIT_CLASS'    => $isExportable ? '' : 'hide-it',
					'COMMENT_CREATED' => $createdTime->getRelativeTime()
				));

				if (!$isExportable && $comment['owner'] == $_SESSION['id'] || $permission >= WRITE_ACCESS) {
					$this->setVar('DELETE_COMMENT', "| <a href=\"/eportfolio/blog/comment/delete/{$comment['id']}\">Delete</a>");
                }
				else {
					$this->setVar('DELETE_COMMENT', '');
                }

				$this->parse('comment_block', 'comment', true);
			}
		}
        else {
			$this->setVar('comment_block', '');
			$this->setVar('show_comment_block', '');
		}

		$this->parse('blog_block', 'blog', true);
	}
}
else {
	$this->setVar('blog_block', '');
}

$this->setBlock($componentHandle, 'submit', 'submit_block');

if ($eportfolioHandler->canEdit($userID)) {
    $this->parse('submit_block', 'submit');
}
else {
	$this->setVar('submit_block', '');
}
?>
