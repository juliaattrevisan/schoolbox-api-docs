<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_ForumListController extends Component_Homepage_ControllerAbstract
{
    /**
     * @var Group
     */
    private $groupHandler;

    private \Schoolbox\Forum\ThreadMapper $threadMapper;

    /**
     * Available sort fields for this component.
     *
     * @var string[]
     */
    protected $sortFields = ['Threads', 'Posts'];

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if (!$this->isSourceRenderable() && !$this->hasContextWriteAccess()) {
            return false;
        }

        $this->groupHandler = new Group();
        $this->threadMapper = \Schoolbox\Forum\ThreadMapper::getInstance();
        return true;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'forumList')) {
            return false;
        }
        if (!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailableTwig($templateHandle);
        }

        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_posts', 'has_posts_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        $threadList   = $this->threadMapper->getRecentThreadList($this->getSource()->getId(), $this->getConfig());

        // don't show forums to parents if they not a direct member of group
        // (Allow parents to read, but not post, if a child is in group)
        $is_post_allowed = !$this->groupHandler->hasChildAccessOnly($this->getContext()->getId());
        if (!$is_post_allowed && Config::get('restrict_parent_forum_access')) {
            return false;
        }

        if (empty($threadList)) {
            $this->setBlock('empty_state', 'empty_write', 'empty_write_block');
            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');

            $this->setVar([
                'has_posts_block'         => '',
                'empty_write_block'       => '',
                'empty_read_block'        => '',
                'COMPONENT_CONFIG_SRC_ID' => $this->getSource()->getId(),
                'SOURCE_ID'               => $this->getSource()->getId(),
            ]);

            if ($is_post_allowed) {
                $this->parse('empty_write_block', 'empty_write');
            } else {
                $this->parse('empty_read_block', 'empty_read');
            }

            $this->parse('empty_state_block', 'empty_state');
        } else {
            $this->setBlock('has_posts', 'source_folder_deleted', 'source_folder_deleted_block');
            $this->setBlock('has_posts', 'source_folder_exists', 'source_folder_exists_block');
            $this->setBlock('source_folder_exists', 'forum', 'forum_block');
            $this->setBlock('source_folder_exists', 'forum_post', 'forum_post_block');
            $this->setBlock('forum', 'forum_reply', 'forum_reply_block');

            // Set up component name
            $this->setVar([
                'empty_state_block'         => '',
                'COMPONENT_CONFIG_SRC_ID'   => $this->getSource()->getId(),
                'COMPONENT_CONFIG_SRC_NAME' => Scrub::text($this->getSource()->getName()),
                'COMPONENT_HANDLE'          => strtolower($templateHandle),
                'COMPONENT_NAME'            => strlen($this->getConfig('componentname')) ? $this->getConfig('componentname') : $this->getComponentName(),
            ]);

            $this->setVar([
                'source_folder_deleted_block' => '',
                'forum_post_block'            => '',
            ]);

            if ($is_post_allowed) {
                $this->parse('forum_post_block', 'forum_post');
            }

            /* Prevent multiple forum entries from other categories stacking up in the wrong place
             * and initialise empty forum roll by default
             */
            $this->setVar("forum_block", '');

            // Iterate
            if (is_array($threadList)) {
                $j           = 0;
                $userHandler = new User();

                foreach ($threadList as $thread) {
                    $user = [
                        'title'     => $thread['userTitle'],
                        'firstname' => $thread['userFirstname'],
                        'lastname'  => $thread['userLastname'],
                    ];

                    $threadTime = new Alaress_Date_Format($thread['date'], 'Y-m-d H:i:s');

                    $this->setVar([
                        'THREAD_ID'               => $thread['threadID'],
                        'THREAD_USER'             => $userHandler->formatName($user),
                        'THREAD_USER_ID'          => $thread['userID'],
                        'THREAD_USER_EXTERNAL_ID' => $thread['synergy_id'],
                        'THREAD_DATE'             => $threadTime->getRelativeTime(),
                        'THREAD_TITLE'            => Scrub::text($thread['title']),
                        'forum_reply_block'       => '',
                    ]);

                    if ($is_post_allowed && ($thread['locked'] != 'true' || $this->getContentPermission() >= UserAccess::ACCESS_FULL)) {
                        $this->parse('forum_reply_block', 'forum_reply');
                    }

                    $this->parse("forum_block", "forum", true);
                }
            } else {
                $this->setVar("forum_block", '');
            }
            $this->parse('source_folder_exists_block', 'source_folder_exists');
            $this->parse('has_posts_block', 'has_posts');
        }

        return true;
    }
}
