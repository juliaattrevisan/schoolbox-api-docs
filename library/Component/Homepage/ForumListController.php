<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use \Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_ForumListController extends Component_Homepage_ControllerAbstract
{
    /**
     * @var Group
     */
    private $groupHandler;

    /**
     * Available sort fields for this component.
     *
     * @var array
     */
    protected $sortFields = array('Threads', 'Posts');

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if(!$this->isSourceRenderable()){
            return $this->hasContextWriteAccess();
        }

        $this->groupHandler = new Group();
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

        $this->parseConfig();

        $forumHandler = new Forum();
        $threadList = $forumHandler->getRecentThreadList($this->getSource()->getId(), $this->getConfig());

        // don't show forums to parents if they not a direct member of group
        // (Allow parents to read, but not post, if a child is in group)
        $is_post_allowed = !$this->groupHandler->hasChildAccessOnly($this->getContext()->getId());
        if (!$is_post_allowed && Cfg::get('restrict_parent_forum_access')) {
            return false;
        }

        if (count($threadList) != 0) {

            $this->setBlock($templateHandle, 'posts', 'posts_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
            $this->setBlock('empty_state', 'empty_modify', 'empty_modify_block');

            $this->setVar('posts_block', '');
            $this->setVar('COMPONENT_CONFIG_SRC_ID', $this->getSource()->getId());
            $this->setVar('SOURCE_ID', $this->getSource()->getId());

            if ($is_post_allowed || $this->getContentPermission() >= UserAccess::ACCESS_FULL) {
                $this->parse("empty_modify_block", "empty_modify");
            } else {
                $this->setVar("empty_modify_block", "");
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            $this->setBlock($templateHandle, 'source_folder_deleted', 'source_folder_deleted_block');
            $this->setBlock($templateHandle, 'source_folder_exists', 'source_folder_exists_block');
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
            $this->setBlock('source_folder_exists', 'forum', 'forum_block');
            $this->setBlock('source_folder_exists', 'forum_post', 'forum_post_block');
            $this->setBlock('forum', 'forum_reply', 'forum_reply_block');

            $this->setVar('empty_state_block', '');

            // Set up component name
            $this->setVar(
                array(
                    'COMPONENT_CONFIG_SRC_ID' => $this->getSource()->getId(),
                    'COMPONENT_CONFIG_SRC_NAME' => $this->getSource()->getName(),
                    'COMPONENT_HANDLE' => strtolower($templateHandle),
                    'COMPONENT_NAME' => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName(),
                )
            );

            if (!$this->isSourceRenderable()) {
                return $this->renderSourceUnavailable();
            } else {
                $this->setVar('source_folder_deleted_block', '');

                $this->setVar('forum_post_block', '');

                if ($is_post_allowed) {
                    $this->parse('forum_post_block', 'forum_post');
                }

                /* Prevent multiple forum entries from other categories stacking up in the wrong place
                 * and initialise empty forum roll by default
                 */
                $this->setVar("forum_block", '');

                // Iterate
                if (is_array($threadList)) {
                    $j = 0;
                    $userHandler = new User();

                    foreach ($threadList as $thread) {
                        $user = array(
                            'title' => $thread['userTitle'],
                            'firstname' => $thread['userFirstname'],
                            'lastname' => $thread['userLastname'],
                        );

                        $threadTime = new Alaress_Date_Format($thread['date'], 'Y-m-d H:i:s');

                        $this->setVar(
                            array(
                                'THREAD_ID' => $thread['threadID'],
                                'THREAD_USER' => $userHandler->formatName($user),
                                'THREAD_USER_ID' => $thread['userID'],
                                'THREAD_USER_EXTERNAL_ID' => $thread['synergy_id'],
                                'THREAD_DATE' => $threadTime->getRelativeTime(),
                                'THREAD_TITLE' => Scrub::text($thread['title']),
                                'ON_OFF' => ++$j % 2 ? '' : 'dark',
                            )
                        );

                        $this->setVar('forum_reply_block', '');

                        if ($is_post_allowed && ($thread['locked'] != 'true' || $this->getContentPermission() >= UserAccess::ACCESS_FULL)) {
                            $this->parse('forum_reply_block', 'forum_reply');
                        }

                        $this->parse("forum_block", "forum", true);
                    }
                } else {
                    $this->setVar("forum_block", '');
                }
                $this->parse('source_folder_exists_block', 'source_folder_exists');
            }
        }

        return true;
    }

    /* Getters / Setters
     */
}
