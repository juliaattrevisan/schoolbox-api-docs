<?php
/**
 * SocialStream_Ajax
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream;

use Scrub;

class Threads
{

    /**
     * A schoolbox collection of SocialStream_Post instances
     *
     * @var null
     */
    private $allPosts = null;

    /**
     * Resultant tree structure
     *
     * @var null
     */
    private $tree = null;

    /**
     * The homepage which you are currently on
     * This is derived from the component instance's identifier value
     *
     * @var int
     */
    private $homepageId = 0;

    /**
     * Only fetch N posts newer than $dateThreshold
     *
     * @var int
     */
    private $start = 0;

    /**
     * Only fetch posts newer than this date
     *
     * @var string
     */
    private $offset = 0;

    /**
     * The component instance
     *
     * @var null
     */
    private $instance = null;

    /**
     * The output html structure
     *
     * @var string
     */
    private $ajaxHtml = '';

    /**
     * Constructor
     *
     * @param $instance
     * @param $homepageId
     * @param $start
     * @param $offset
     */
    public function __construct($instance, $homepageId, $start, $offset)
    {
        $this->setInstance($instance);
        $this->setHomepageId($homepageId);
        $this->setStart($start);
        $this->setOffset($offset);

        $this->init();
        $this->growTree();
    }

    /**
     * Initialise data set before growing tree
     */
    private function init()
    {

        /* Get all the posts that should appear
         */
        $allPosts = \SocialStream\PostMapper::getInstance()->findLatestForHomepage($this->getHomepageId(), $this->getStart(), $this->getOffset());

        $ids = array();

        foreach ($allPosts as $currentPost) {
            $ids[] = $currentPost->getPostId();
        }

        if (count($ids)) {
            /* Get each posts' hierarchy
             */
            $tree = \SocialStream\PostMapper::getInstance()->findHierarchy($ids);

            /* And merge it all together
             */
            $tree = $tree->merge($allPosts);
        } else {
            $tree = new \Schoolbox\Collection();
        }

        /* Save it for later!
         */
        $this->setAllPosts($tree);
    }

    /**
     * Grow a tree
     *
     * @return array
     */
    private function growTree()
    {
        $structure = array();
        $remove    = array();

        /* Move everything into an array to start with
         */
        foreach ($this->getAllPosts() as $currentPost) {
            $structure[] = $currentPost;
        }

        /* Push posts into their respective parents
         */
        foreach ($structure as $currentPost) {
            /* Only worry about moving deeper posts
                         */
            if ($currentPost->getParentId()) {
                $destination = $this->findNode($structure, $currentPost->getParentId());

                /* Only try to add it if a destination is found
                 * (This should get around orphaned posts)
                 */
                if ($destination) {
                    $destination->getChildren()->add($currentPost);
                }

                /* Remember which ones we can remove from the root level
                 */
                $remove[] = $currentPost->getPostId();
            }
        }

        /* Nullify posts which have been moved elsewhere
         */
        for ($i = 0; $i < count($structure); $i++) {
            if (in_array($structure[$i]->getPostId(), $remove)) {
                $structure[$i] = null;
            }
        }

        /* Remove posts which were nullified
         */
        $tree = array_values(array_filter($structure));

        /* Transform it back into a collection, because we normally don't
         * remove elements from iterator classes
         */
        $collection = new \Schoolbox\Collection();

        foreach ($tree as $node) {
            $collection->add($node);
        }

        /* Save that one for later!
         */
        $this->setTree($collection);

        return $this;
    }

    /**
     * Find a node in a tree
     *
     * @param $tree
     * @param $id
     *
     * @return mixed
     */
    private function findNode($tree, $id)
    {
        foreach ($tree as $leaf) {
            if ($leaf->getPostId() == $id) {
                return $leaf;
            }
        }
    }

    /**
     * Generate ajax html
     */
    public function generate()
    {
        $page = new \SitePage();

        /* Determine whether you have access to the input form
         */
        $newPost       = new \SocialStream_Post_String();
        $currentUser   = $_SESSION['user'];
        $authenticator = new Authenticator($newPost, $this->getInstance(), $currentUser);

        /* Simply hide the comment icon if you can't view it
         */
        if ($authenticator->canComment()) {
            $canComment = true;
        } else {
            $canComment = false;
        }

        /* Set up blocks and stuff
         */
        $page->setFile('socialstream_thread', '/component/homepage/socialstream/thread.html');
        $page->setBlock('socialstream_thread', 'socialstream_post', 'socialstream_post_block');
        $page->setBlock('socialstream_post', 'socialstream_comment', 'socialstream_comment_block');
        $page->setBlock('socialstream_post', 'owner_exists', 'owner_exists_block');
        $page->setBlock('socialstream_post', 'owner_deleted', 'owner_deleted_block');
        $page->setBlock('socialstream_comment', 'socialstream_reply', 'socialstream_reply_block');

        /* Thumbnail stuff
         */
        $page->setBlock('socialstream_post', 'socialstream_post_has_attachment', 'socialstream_post_has_attachment_block');
        $page->setBlock('socialstream_post_has_attachment', 'socialstream_post_has_icon', 'socialstream_post_has_icon_block');
        $page->setBlock('socialstream_post_has_attachment', 'socialstream_post_has_thumbnail', 'socialstream_post_has_thumbnail_block');
        $page->setBlock('socialstream_post_has_attachment', 'socialstream_post_has_link', 'socialstream_post_has_link_block');
        $page->setBlock('socialstream_post_has_attachment', 'socialstream_post_has_player', 'socialstream_post_has_player_block');
        $page->setBlock('socialstream_post_has_attachment', 'socialstream_post_has_video', 'socialstream_post_has_video_block');
        $page->setBlock('socialstream_post_has_attachment', 'socialstream_post_has_video_pending', 'socialstream_post_has_video_pending_block');
        $page->setBlock('socialstream_post_has_attachment', 'socialstream_post_has_metadata', 'socialstream_post_has_metadata_block');
        $page->setBlock('socialstream_post_has_attachment', 'socialstream_post_has_image', 'socialstream_post_has_image_block');
        $page->setBlock('socialstream_post', 'socialstream_post_has_add', 'socialstream_post_has_add_block');
        $page->setBlock('socialstream_post', 'socialstream_post_has_delete', 'socialstream_post_has_delete_block');

        $page->setBlock('socialstream_comment', 'socialstream_comment_has_attachment', 'socialstream_comment_has_attachment_block');
        $page->setBlock('socialstream_comment_has_attachment', 'socialstream_comment_has_icon', 'socialstream_comment_has_icon_block');
        $page->setBlock('socialstream_comment_has_attachment', 'socialstream_comment_has_thumbnail', 'socialstream_comment_has_thumbnail_block');
        $page->setBlock('socialstream_comment_has_attachment', 'socialstream_comment_has_link', 'socialstream_comment_has_link_block');
        $page->setBlock('socialstream_comment_has_attachment', 'socialstream_comment_has_player', 'socialstream_comment_has_player_block');
        $page->setBlock('socialstream_comment_has_attachment', 'socialstream_comment_has_video', 'socialstream_comment_has_video_block');
        $page->setBlock('socialstream_comment_has_attachment', 'socialstream_comment_has_video_pending', 'socialstream_comment_has_video_pending_block');
        $page->setBlock('socialstream_comment_has_attachment', 'socialstream_comment_has_metadata', 'socialstream_comment_has_metadata_block');
        $page->setBlock('socialstream_comment_has_attachment', 'socialstream_comment_has_image', 'socialstream_comment_has_image_block');
        $page->setBlock('socialstream_comment', 'commenter_exists', 'commenter_exists_block');
        $page->setBlock('socialstream_comment', 'commenter_deleted', 'commenter_deleted_block');
        $page->setBlock('socialstream_comment', 'socialstream_comment_has_add', 'socialstream_comment_has_add_block');
        $page->setBlock('socialstream_comment', 'socialstream_comment_has_delete', 'socialstream_comment_has_delete_block');

        $page->setBlock('socialstream_reply', 'socialstream_reply_has_attachment', 'socialstream_reply_has_attachment_block');
        $page->setBlock('socialstream_reply_has_attachment', 'socialstream_reply_has_icon', 'socialstream_reply_has_icon_block');
        $page->setBlock('socialstream_reply_has_attachment', 'socialstream_reply_has_thumbnail', 'socialstream_reply_has_thumbnail_block');
        $page->setBlock('socialstream_reply_has_attachment', 'socialstream_reply_has_link', 'socialstream_reply_has_link_block');
        $page->setBlock('socialstream_reply_has_attachment', 'socialstream_reply_has_player', 'socialstream_reply_has_player_block');
        $page->setBlock('socialstream_reply_has_attachment', 'socialstream_reply_has_video', 'socialstream_reply_has_video_block');
        $page->setBlock('socialstream_reply_has_attachment', 'socialstream_reply_has_video_pending', 'socialstream_reply_has_video_pending_block');
        $page->setBlock('socialstream_reply_has_attachment', 'socialstream_reply_has_metadata', 'socialstream_reply_has_metadata_block');
        $page->setBlock('socialstream_reply_has_attachment', 'socialstream_reply_has_image', 'socialstream_reply_has_image_block');
        $page->setBlock('socialstream_reply', 'socialstream_reply_has_delete', 'socialstream_reply_has_delete_block');
        $page->setBlock('socialstream_reply', 'replier_exists', 'replier_exists_block');
        $page->setBlock('socialstream_reply', 'replier_deleted', 'replier_deleted_block');


        $page->setVar(
            array(
                'socialstream_post_block'                      => '',
                'socialstream_comment_block'                   => '',
                'socialstream_reply_block'                     => '',
                'socialstream_post_has_attachment_block'       => '',
                'socialstream_post_has_icon_block'             => '',
                'socialstream_post_has_image_block'            => '',
                'socialstream_post_has_thumbnail_block'        => '',
                'socialstream_post_has_link_block'             => '',
                'socialstream_post_has_player_block'           => '',
                'socialstream_post_has_video_block'            => '',
                'socialstream_post_has_video_pending_block'    => '',
                'socialstream_post_has_metadata_block'         => '',
                'socialstream_post_has_delete_block'           => '',
                'socialstream_comment_has_attachment_block'    => '',
                'socialstream_comment_has_icon_block'          => '',
                'socialstream_comment_has_image_block'         => '',
                'socialstream_comment_has_thumbnail_block'     => '',
                'socialstream_comment_has_link_block'             => '',
                'socialstream_comment_has_player_block'        => '',
                'socialstream_comment_has_video_block'         => '',
                'socialstream_comment_has_video_pending_block' => '',
                'socialstream_comment_has_metadata_block'      => '',
                'socialstream_comment_has_delete_block'        => '',
                'socialstream_reply_has_attachment_block'      => '',
                'socialstream_reply_has_icon_block'            => '',
                'socialstream_reply_has_image_block'           => '',
                'socialstream_reply_has_thumbnail_block'       => '',
                'socialstream_reply_has_link_block'             => '',
                'socialstream_reply_has_player_block'          => '',
                'socialstream_reply_has_video_block'           => '',
                'socialstream_reply_has_video_pending_block'   => '',
                'socialstream_reply_has_metadata_block'        => '',
                'socialstream_reply_has_delete_block'          => '',
            )
        );

        /* Traverse everything
         */
        $i = 0;

        foreach ($this->getTree() as $currentPost) {
            $page->setVar(
                array(
                    'COMPONENT_INSTANCE_ID'              => Scrub::text($this->getInstance()->getId()),
                    'SOCIALSTREAM_POST_POST_ID'          => Scrub::text($currentPost->getPostId()),
                    'SOCIALSTREAM_POST_BODY'             => nl2br(Scrub::text($currentPost->getBody())),
                    'SOCIALSTREAM_POST_CREATED_AT'       => Scrub::text($currentPost->getCreatedAt()->getRelativeTime()),
                    'SOCIALSTREAM_POST_CREATED_AT_LONG'  => Scrub::text($currentPost->getCreatedAt()->getLong()),
                    'SOCIALSTREAM_POST_USER_ID'          => Scrub::text($currentPost->getOwner()->getId()),
                    'SOCIALSTREAM_POST_USER_EXTERNAL_ID' => Scrub::text($currentPost->getOwner()->getExternalId()),
                    'SOCIALSTREAM_POST_USER_FULLNAME'    => Scrub::text($currentPost->getOwner()->getFullname()),
                    'SOCIALSTREAM_POST_TIMESTAMP'        => Scrub::text($currentPost->getCreatedAt()->getUnixTimestamp()),
                )
            );

            // Owner name and optional link
            if($currentPost->getOwner()->isDeleted()){
                $page->setVar('owner_exists_block', '');
                $page->parse('owner_deleted_block', 'owner_deleted');
            } else {
                $page->parse('owner_exists_block', 'owner_exists');
                $page->setVar('owner_deleted_block', '');
            }

            /* Ability to add posts
             */
            if ($canComment) {
                $page->parse('socialstream_post_has_add_block', 'socialstream_post_has_add');
            } else {
                $page->setVar(
                    array(
                        'socialstream_post_has_add_block' => '',
                    )
                );
            }

            /* Ability to delete posts
             */
            $authenticator->setNewPost($currentPost);

            if ($authenticator->canDelete()) {
                $page->parse('socialstream_post_has_delete_block', 'socialstream_post_has_delete');
            } else {
                $page->setVar(
                    array(
                        'socialstream_post_has_delete_block' => '',
                    )
                );
            }

            /* Thumbnails
             */
            $page->setVar(
                array(
                    'socialstream_post_has_attachment_block'    => '',
                    'socialstream_post_has_icon_block'          => '',
                    'socialstream_post_has_image_block'         => '',
                    'socialstream_post_has_thumbnail_block'     => '',
                    'socialstream_post_has_link_block'          => '',
                    'socialstream_post_has_player_block'        => '',
                    'socialstream_post_has_video_block'         => '',
                    'socialstream_post_has_video_pending_block' => '',
                    'socialstream_post_has_metadata_block'      => '',
                )
            );

            $hasAttachment = $currentPost->renderThumbnail('post', $page);

            if ($hasAttachment) {
                $page->parse('socialstream_post_has_attachment_block', 'socialstream_post_has_attachment');
            }

            /* Descendents
             */
            foreach ($currentPost->getChildren() as $currentComment) {
                $page->setVar(
                    array(
                        'COMPONENT_INSTANCE_ID'                 => Scrub::text($this->getInstance()->getId()),
                        'SOCIALSTREAM_COMMENT_POST_ID'          => Scrub::text($currentComment->getPostId()),
                        'SOCIALSTREAM_COMMENT_BODY'             => nl2br(Scrub::text($currentComment->getBody())),
                        'SOCIALSTREAM_COMMENT_CREATED_AT'       => Scrub::text($currentComment->getCreatedAt()->getRelativeTime()),
                        'SOCIALSTREAM_COMMENT_USER_ID'          => Scrub::text($currentComment->getOwner()->getId()),
                        'SOCIALSTREAM_COMMENT_USER_EXTERNAL_ID' => Scrub::text($currentComment->getOwner()->getExternalId()),
                        'SOCIALSTREAM_COMMENT_USER_FULLNAME'    => Scrub::text($currentComment->getOwner()->getFullname()),
                    )
                );

                // Commenter name and optional link
                if($currentComment->getOwner()->isDeleted()){
                    $page->setVar('commenter_exists_block', '');
                    $page->parse('commenter_deleted_block', 'commenter_deleted');
                } else {
                    $page->parse('commenter_exists_block', 'commenter_exists');
                    $page->setVar('commenter_deleted_block', '');
                }

                /* Ability to add posts
                 */
                if ($canComment) {
                    $page->parse('socialstream_comment_has_add_block', 'socialstream_comment_has_add');
                } else {
                    $page->setVar(
                        array(
                            'socialstream_comment_has_add_block' => '',
                        )
                    );
                }

                /* Ability to delete posts
                 */
                $authenticator->setNewPost($currentComment);

                if ($authenticator->canDelete()) {
                    $page->parse('socialstream_comment_has_delete_block', 'socialstream_comment_has_delete');
                } else {
                    $page->setVar(
                        array(
                            'socialstream_comment_has_delete_block' => '',
                        )
                    );
                }

                /* Thumbnails
                 */
                $page->setVar(
                    array(
                        'socialstream_comment_has_attachment_block'    => '',
                        'socialstream_comment_has_icon_block'          => '',
                        'socialstream_comment_has_image_block'         => '',
                        'socialstream_comment_has_thumbnail_block'     => '',
                        'socialstream_comment_has_link_block'          => '',
                        'socialstream_comment_has_player_block'        => '',
                        'socialstream_comment_has_video_block'         => '',
                        'socialstream_comment_has_video_pending_block' => '',
                        'socialstream_comment_has_metadata_block'      => '',
                    )
                );

                $hasAttachment = $currentComment->renderThumbnail('comment', $page);

                if ($hasAttachment) {
                    $page->parse('socialstream_comment_has_attachment_block', 'socialstream_comment_has_attachment');
                }

                /* Descendents
                 */
                foreach ($currentComment->getChildren() as $currentReply) {
                    $page->setVar(
                        array(
                            'COMPONENT_INSTANCE_ID'               => Scrub::text($this->getInstance()->getId()),
                            'SOCIALSTREAM_REPLY_POST_ID'          => Scrub::text($currentReply->getPostId()),
                            'SOCIALSTREAM_REPLY_BODY'             => nl2br(Scrub::text($currentReply->getBody())),
                            'SOCIALSTREAM_REPLY_CREATED_AT'       => Scrub::text($currentReply->getCreatedAt()->getRelativeTime()),
                            'SOCIALSTREAM_REPLY_USER_ID'          => Scrub::text($currentReply->getOwner()->getId()),
                            'SOCIALSTREAM_REPLY_USER_EXTERNAL_ID' => Scrub::text($currentReply->getOwner()->getExternalId()),
                            'SOCIALSTREAM_REPLY_USER_FULLNAME'    => Scrub::text($currentReply->getOwner()->getFullname()),
                        )
                    );

                    /* Ability to delete posts
                     */
                    $authenticator->setNewPost($currentReply);

                    if ($authenticator->canDelete()) {
                        $page->parse('socialstream_reply_has_delete_block', 'socialstream_reply_has_delete');
                    } else {
                        $page->setVar(
                            array(
                                'socialstream_reply_has_delete_block' => '',
                            )
                        );
                    }

                    /* Thumbnails
                     */
                    $page->setVar(
                        array(
                            'socialstream_reply_has_attachment_block'    => '',
                            'socialstream_reply_has_icon_block'          => '',
                            'socialstream_reply_has_image_block'         => '',
                            'socialstream_reply_has_thumbnail_block'     => '',
                            'socialstream_reply_has_link_block'          => '',
                            'socialstream_reply_has_player_block'        => '',
                            'socialstream_reply_has_video_block'         => '',
                            'socialstream_reply_has_video_pending_block' => '',
                            'socialstream_reply_has_metadata_block'      => '',
                        )
                    );

                    // Replier name and optional link
                    if($currentReply->getOwner()->isDeleted()){
                        $page->setVar('replier_exists_block', '');
                        $page->parse('replier_deleted_block', 'replier_deleted');
                    } else {
                        $page->parse('replier_exists_block', 'replier_exists');
                        $page->setVar('replier_deleted_block', '');
                    }

                    $hasAttachment = $currentReply->renderThumbnail('reply', $page);

                    if ($hasAttachment) {
                        $page->parse('socialstream_reply_has_attachment_block', 'socialstream_reply_has_attachment');
                    }

                    /* Clear children after parsing out the parent
                     */
                    $page->parse('socialstream_reply_block', 'socialstream_reply', true);

                    $page->setVar(
                        array(
                            'socialstream_reply_has_attachment_block'    => '',
                            'socialstream_reply_has_icon_block'          => '',
                            'socialstream_reply_has_thumbnail_block'     => '',
                            'socialstream_reply_has_link_block'          => '',
                            'socialstream_reply_has_image_block'         => '',
                            'socialstream_reply_has_player_block'        => '',
                            'socialstream_reply_has_video_block'         => '',
                            'socialstream_reply_has_video_pending_block' => '',
                            'socialstream_reply_has_metadata_block'      => '',
                            'socialstream_reply_has_add_block'           => '',
                            'socialstream_reply_has_delete_block'        => '',
                        )
                    );
                }

                /* Clear children after parsing out the parent
                 */
                $page->parse('socialstream_comment_block', 'socialstream_comment', true);

                $page->setVar(
                    array(
                        'socialstream_comment_has_attachment_block'    => '',
                        'socialstream_comment_has_icon_block'          => '',
                        'socialstream_comment_has_image_block'         => '',
                        'socialstream_comment_has_thumbnail_block'     => '',
                        'socialstream_comment_has_link_block'          => '',
                        'socialstream_comment_has_player_block'        => '',
                        'socialstream_comment_has_video_block'         => '',
                        'socialstream_comment_has_video_pending_block' => '',
                        'socialstream_comment_has_metadata_block'      => '',
                        'socialstream_comment_has_add_block'           => '',
                        'socialstream_comment_has_delete_block'        => '',
                        'socialstream_reply_block'                     => '',
                    )
                );
            }

            /* Clear children after parsing out the parent
             */
            $page->parse('socialstream_post_block', 'socialstream_post', true);

            $page->setVar(
                array(
                    'socialstream_post_has_attachment_block'    => '',
                    'socialstream_post_has_icon_block'          => '',
                    'socialstream_post_has_image_block'         => '',
                    'socialstream_post_has_thumbnail_block'     => '',
                    'socialstream_post_has_link_block'          => '',
                    'socialstream_post_has_player_block'        => '',
                    'socialstream_post_has_video_block'         => '',
                    'socialstream_post_has_video_pending_block' => '',
                    'socialstream_post_has_metadata_block'      => '',
                    'socialstream_post_has_add_block'           => '',
                    'socialstream_post_has_delete_block'        => '',
                    'socialstream_comment_block'                => '',
                    'socialstream_reply_block'                  => '',
                )
            );

            /* Alternating colours -_-
             */
            $i++;
        }

        /* Save output for later!
         */
        $this->setAjaxHtml($page->template->_varVals['socialstream_post_block']);

        /* Chain it
         */
        return $this;
    }

    /**
     * Render out a bunch of html containing each SocialStream Post
     */
    public function render()
    {
        return $this->getAjaxHtml();
    }

    /* Getters/Setters
     */

    /**
     * @param null $allPosts
     */
    private function setAllPosts($allPosts)
    {
        $this->allPosts = $allPosts;
    }

    /**
     * @return null
     */
    private function getAllPosts()
    {
        return $this->allPosts;
    }

    /**
     * Set tree
     *
     * @param null $tree
     */
    private function setTree($tree)
    {
        $this->tree = $tree;
    }

    /**
     * Get tree
     *
     * @return \SocialStream_Post_Abstract[]
     */
    private function getTree()
    {
        return $this->tree;
    }

    /**
     * Set homepage id
     *
     * @param $homepageId
     */
    private function setHomepageId($homepageId)
    {
        $this->homepageId = $homepageId;
    }

    /**
     * Get homepage id
     *
     * @return int
     */
    private function getHomepageId()
    {
        return $this->homepageId;
    }

    /**
     * Set instance
     *
     * @param null $instance
     */
    private function setInstance($instance)
    {
        $this->instance = $instance;
    }

    /**
     * Get instance
     *
     * @return null
     */
    private function getInstance()
    {
        return $this->instance;
    }

    /**
     * Set ajax html
     *
     * @param string $ajaxHtml
     */
    private function setAjaxHtml($ajaxHtml)
    {
        $this->ajaxHtml = $ajaxHtml;
    }

    /**
     * Get ajax html
     *
     * @return string
     */
    private function getAjaxHtml()
    {
        return $this->ajaxHtml;
    }

    /**
     * Set start
     *
     * @param int $start
     */
    public function setStart($start)
    {
        $this->start = $start;
    }

    /**
     * Get start
     *
     * @return int
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Set offset
     *
     * @param string $offset
     */
    private function setOffset($offset)
    {
        $this->offset = $offset;
    }

    /**
     * Get offset
     *
     * @return string
     */
    private function getOffset()
    {
        return $this->offset;
    }
}
