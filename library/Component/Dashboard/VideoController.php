<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\File;
use Schoolbox\Video\ResourceVideo;

class Component_Dashboard_VideoController extends Component_Dashboard_ControllerAbstract
{
    /**
     * @var Collection|ResourceVideo[]
     */
    protected $outputList;

    /**
     * @var \Schoolbox\Video\ResourceVideo
     */
    protected $defaultVideo;

    /**
     * Source category for this component.
     *
     * @var int
     */
    protected $source_category_id;

    /**
     * Available sort fields for this component.
     *
     * @var string[]
     */
    protected $sortFields = array('name', 'date');

    /**
     * Determine whether the current user should be able to see the video given as an argument.
     *
     * @param ResourceVideo $video
     *
     * @return boolean
     */
    private function isVisibleToCurrentUser(ResourceVideo $video): bool
    {
        if (!($video->getFile() instanceof File\Model)) {
            return false;
        }

        // Read access users should not be able to see hidden videos.
        return !$video->getFile()->getHidden() || $this->getContentPermission() > UserAccess::ACCESS_READ;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'video')) {
            return false;
        }

        $this->parseConfig();

        $source = $this->getConfig('src');

        if (empty($source)) {
            // Nothing to see here move along
            return false;
        }

        $this->source_category_id = $source;

        if ($this->getContentPermission() < UserAccess::ACCESS_READ) {
            return false;
        }

        /* Sort order
         */
        $sortConfig = array(
            'sort'    => $this->getConfig('sort'),
            'sortdir' => $this->getConfig('sortdir')
        );

        $videoResourceMapper = new \Schoolbox\Video\ResourceVideoMapper();

        if ($this->getConfig('size') > 1) {
            $this->outputList = $videoResourceMapper->findByCategory($source, $sortConfig, $this->getConfig('size') - 1);
        } else {
            if ($this->getConfig('size') == 0) {
                $this->outputList = $videoResourceMapper->findByCategory($source, $sortConfig);
            } else {
                $this->outputList = new \Schoolbox\Collection(array());
            }
        }

        if (!$this->outputList->count()) {
            return false; // No videos found on configured source page.  Behave as if no source set.
        }

        if ($this->getConfig('video')) {
            try {
                $video = $videoResourceMapper->findByFileId($this->getConfig('video'));

                if (
                    $video->isComplete()
                    && $video->getFile() instanceof File\Model
                    && $video->getFile()->getFolder()->getId() == $this->getSource()->getId()
                    && $this->isVisibleToCurrentUser($video)
                ) {
                    $this->defaultVideo = $video;
                }
            } catch (\Schoolbox\Mapper\Exception $e) {
                // Ok to ignore. The video referenced by config no longer exists
                $this->defaultVideo = $this->outputList->first();
            }
        }

        /* If defaultVideo is null, then we couldn't display the video that was originally chosen in the component config
         * We must look for an alternative among the others in $outputList
         */
        if (null === $this->defaultVideo) {
            // Show the first video that has completed encoding
            foreach ($this->outputList as $video) {
                /** @var ResourceVideo $video */
                // Don't pick a video not visible to the user as the default video
                if ($video->isComplete() && $this->isVisibleToCurrentUser($video)) {
                    $this->defaultVideo = $video;
                    break;
                }
            }
        }

        // Set up component name
        $this->setVar(
            array(
                'COMPONENT_NAME' => strlen($this->getConfig('componentname')) ? Scrub::text($this->getConfig('componentname')) : 'Video',
                'FILE_CAT_ID'    => $this->source_category_id,
            )
        );

        $this->setBlock($templateHandle, 'default_video', 'default_video_block');
        $this->setBlock($templateHandle, 'video_list', 'video_list_block');
        $this->setBlock($templateHandle, 'video_pending_list', 'video_pending_list_block');
        $this->setBlock('video_list', 'video_list_comment', 'video_list_comment_block');
        $this->setBlock('video_list', 'owner_exists', 'owner_exists_block');
        $this->setBlock('video_list', 'owner_deleted', 'owner_deleted_block');
        $this->setBlock('video_pending_list', 'video_pending_comment', 'video_pending_comment_block');

        $this->setVar('video_list_block', '');
        $this->setVar('video_pending_list_block', '');
        $this->setVar('default_video_block', '');

        $this->setVar(array(
            'video_list_comment_block' => '',
            'video_pending_comment_block' => ''
        ));

        /* Number of videos that were shown in the list
         *
         * Because the visibility check is done in the iteration we won't know how many videos from
         * $outputList were actually shown to the user; it could be none (at which point this function
         * should return false).
         */
        $numVideosListed = 0;

        foreach ($this->outputList as $video) {
            $this->setVar(
                array(
                    'VIDEO_LIST_ID'        => $video->getFile()->getId(),
                    'VIDEO_LIST_TITLE'     => Scrub::text($video->getFile()->getTitle()),
                    'VIDEO_LIST_DURATION'  => $video->getDuration() >= 3600 ? gmdate('H:i:s', $video->getDuration()) : gmdate('i:s', $video->getDuration()),
                    'VIDEO_LIST_POSTER_ID' => $video->getFile()->getOwner()->getId(),
                    'VIDEO_LIST_POSTER'    => $video->getFile()->getOwner()->getFullName(),
                )
            );

            $comment = Scrub::text($video->getFile()->getDescription());

            if ($video->getStatus() === "completed" && $this->isVisibleToCurrentUser($video)) {
                if (strlen($comment)) {
                    $this->setVar(array(
                        'VIDEO_LIST_COMMENT' => $comment
                    ));

                    $this->parse('video_list_comment_block', 'video_list_comment');
                } else {
                    $this->setVar(array(
                        'video_list_comment_block' => ''
                    ));
                }

                // Owner name and optional link
                if ($video->getFile()->getOwner()->isDeleted()) {
                    $this->setVar('owner_exists_block', '');
                    $this->parse('owner_deleted_block', 'owner_deleted');
                } else {
                    $this->parse('owner_exists_block', 'owner_exists');
                    $this->setVar('owner_deleted_block', '');
                }

                $this->parse('video_list_block', 'video_list', true);

                $numVideosListed++;
            } else {
                if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                    if (strlen($comment)) {
                        $this->setVar(array(
                            'VIDEO_PENDING_COMMENT' => $comment
                        ));

                        $this->parse('video_pending_comment_block', 'video_pending_comment');
                    } else {
                        $this->setVar(array(
                            'video_pending_comment_block' => ''
                        ));
                    }

                    $this->setVar('VIDEO_LIST_STATUS', strtolower($video->getStatus()));

                    $this->parse('video_pending_list_block', 'video_pending_list', true);

                    $numVideosListed++;
                }
            }

            /* Clear children after parsing out the parent
             */
            $this->setVar(array(
                'video_list_comment_block' => '',
                'video_pending_comment_block' => ''
            ));
        }

        if ($numVideosListed === 0 && ($this->getContentPermission() < UserAccess::ACCESS_WRITE)) {
            return false;
        }

        if ($this->defaultVideo->getStatus() === 'completed') {
            $this->setVar('DEFAULT_VIDEO_ID', $this->defaultVideo->getFile()->getId());
            $this->parse('default_video_block', 'default_video');
        } else {
            $this->setVar('default_video_block', '');
        }

        return true; // everything went according to plan
    }
}
