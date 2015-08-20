<?php
/**
 * Display due work
 */

$this->setBlock('REPORT_CONTENT', 'month_head', 'month_head_block' );
$this->setBlock('REPORT_CONTENT', 'social_month_body', 'social_month_body_block' );
$this->setBlock('REPORT_CONTENT', 'forum_month_body', 'forum_month_body_block' );
$this->setBlock('REPORT_CONTENT', 'blog_month_body', 'blog_month_body_block' );
$this->setBlock('REPORT_CONTENT', 'comment_month_body', 'comment_month_body_block' );
$this->setBlock('REPORT_CONTENT', 'wiki_month_body', 'wiki_month_body_block' );
$this->setBlock('REPORT_CONTENT', 'poll_month_body', 'poll_month_body_block' );




$months = $start->diffInMonths($end);

/** @var Alaress_Date_Format $currentMonth   */
$currentMonth = clone $start;

$socialReport = new Schoolbox\Engagement\SocialStream();
$forumReport = new Schoolbox\Engagement\Forum();
$blogReport = new Schoolbox\Engagement\Blog();
$wikiReport = new Schoolbox\Engagement\Wiki();
$pollReport = new Schoolbox\Engagement\Poll();


for($i=0; $i < $months; $i++) {

    $this->setVar('MONTH', $currentMonth->getUsingFormat('F Y'));
    $this->parse('month_head_block', 'month_head', true);


    $endMonth = clone $currentMonth;
    $endMonth->endOfMonth();


    $social = $socialReport->created($currentMonth, $endMonth);
    $this->setVar('SOCIAL_MONTH_COUNT', $social);
    $this->parse('social_month_body_block', 'social_month_body', true);

    $forum = $forumReport->created($currentMonth, $endMonth);
    $this->setVar('FORUM_MONTH_COUNT', $forum);
    $this->parse('forum_month_body_block', 'forum_month_body', true);

    $blog = $blogReport->createdPost($currentMonth, $endMonth);
    $this->setVar('BLOG_MONTH_COUNT', $blog);
    $this->parse('blog_month_body_block', 'blog_month_body', true);

    $comment = $blogReport->createdComment($currentMonth, $endMonth);
    $this->setVar('COMMENT_MONTH_COUNT', $comment);
    $this->parse('comment_month_body_block', 'comment_month_body', true);

    $wiki = $wikiReport->revision($currentMonth, $endMonth);
    $this->setVar('WIKI_MONTH_COUNT', $wiki);
    $this->parse('wiki_month_body_block', 'wiki_month_body', true);

    $poll = $pollReport->votes($currentMonth, $endMonth);
    $this->setVar('POLL_MONTH_COUNT', $poll);
    $this->parse('poll_month_body_block', 'poll_month_body', true);

    $currentMonth->addMonths(1);
}