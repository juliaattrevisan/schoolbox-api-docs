<?php

/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Video\ResourceVideo;
use Schoolbox\Video\ResourceVideoMapper;

class Component_Homepage_VideoController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var Collection|ResourceVideo[]
     */
    protected $outputList;

    /**
     * @var ResourceVideo
     */
    protected $defaultVideo;

    /**
     * Available sort fields for this component.
     *
     * @var array
     */
    protected $sortFields = ['name', 'date'];

    /**
     * Determine whether the current user should be able to see the video given as an argument.
     *
     * @param ResourceVideo $video
     *
     * @return boolean
     */
    private function isVisibleToCurrentUser($video): bool
    {
        // Read access users should not be able to see hidden videos.
        return !$video->getFile()->getHidden() || $this->getContentPermission() > UserAccess::ACCESS_READ;
    }


    /**
     * Build the empty state phpliberties
     */
    private function buildEmptyState()
    {
        $this->setVar(
            [
                'has_video_block'   => '',
                'empty_read_block'  => '',
                'empty_write_block' => '',
                'SOURCE_ID'         => '',
            ]
        );

        $this->parse('empty_state_block', 'empty_state');
    }


    /**
     * Renders the data into the SitePage. Presentation logic.
     *
     * @param String $templateHandle
     *
     * @return boolean
     * @throws PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'video')) {
            return false;
        }

        /* Check access perms first
         */
        if ($this->getContentPermission() < UserAccess::ACCESS_READ) {
            return false;
        }

        if (!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailableTwig($templateHandle);
        }

        /* Need title injected here
         */
        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_video', 'has_video_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        $videoResourceMapper = ResourceVideoMapper::getInstance();

        /* Sort order
         */
        $limit = (int)$this->getConfig('size');

        $this->outputList = $videoResourceMapper->findByCategory(
            $this->getSource()->getId(),
            [
                'sort'    => $this->getConfig('sort'),
                'sortdir' => $this->getConfig('sortdir'),
                'mime'    => 'video',
            ],
            $limit
        );

        // Set up component name
        $this->setVar('FILE_CAT_ID', $this->getSource()->getId());

        /* No videos found
         */
        if (!$this->outputList->count()) {
            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                return false;
            }
            $this->buildEmptyState();

            return true;
        }

        /* Render full list
         */
        $this->setBlock('has_video', 'source_folder_deleted', 'source_folder_deleted_block');
        $this->setBlock('has_video', 'source_folder_exists', 'source_folder_exists_block');
        $this->setBlock('source_folder_exists', 'default_video', 'default_video_block');
        $this->setBlock('source_folder_exists', 'video_list', 'video_list_block');
        $this->setBlock('source_folder_exists', 'video_pending_list', 'video_pending_list_block');
        $this->setBlock('source_folder_exists', 'submit_video', 'submit_video_block');
        $this->setBlock('video_list', 'owner_exists', 'owner_exists_block');
        $this->setBlock('video_list', 'owner_deleted', 'owner_deleted_block');
        $this->setBlock('video_list', 'video_edit', 'video_edit_block');

        $this->setVar(
            [
                'empty_state_block'           => '',
                'source_folder_deleted_block' => '',
                'video_list_block'            => '',
                'video_pending_list_block'    => '',
                'default_video_block'         => '',
            ]
        );

        // Select a video to play by default.
        // Only show a video that has actually finished encoding, though.
        $this->defaultVideo = null;

        if ($this->getConfig('video')) {
            try {
                $video = $videoResourceMapper->findByFileId($this->getConfig('video'));

                if ($video->isComplete()
                    && $video->getFile()->getFolder()->getId() == $this->getSource()->getId()
                    && $this->isVisibleToCurrentUser($video)) {
                    $this->defaultVideo = $video;
                }
            } catch (\Schoolbox\Mapper\Exception $e) {
                // passthru: figure out which video later
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

        /* Number of videos that were shown in the list
         *
         * Because the visibility check is done in the iteration we won't know how many videos from
         * $outputList were actually shown to the user; it could be none (at which point you need to
         * render an empty state)
         */
        $numVideosListed = 0;

        foreach ($this->outputList as $video) {
            // Display the list of videos if the user has permission to view them.
            if ($this->isVisibleToCurrentUser($video)) {
                /* Apply values only if you can see them
                 */
                $this->setVar(
                    [
                        'VIDEO_LIST_ID'        => $video->getFile()->getId(),
                        'VIDEO_LIST_TITLE'     => Scrub::text($video->getFile()->getTitle()),
                        'VIDEO_LIST_DURATION'  => gmdate($video->getDuration() >= 3600 ? 'H:i:s' : 'i:s', $video->getDuration()),
                        'VIDEO_LIST_COMMENT'   => Scrub::text($video->getFile()->getDescription()),
                        'VIDEO_LIST_HIDDEN'    => $video->getFile()->getHidden() ? 'hidden' : '',
                        'VIDEO_LIST_POSTER_ID' => $video->getFile()->getOwner()->getId(),
                        'VIDEO_LIST_POSTER'    => $video->getFile()->getOwner()->getFullName(),
                    ]
                );


                // Owner and edit blocks are children of the video list item,
                // so should not appear unless we are parsing the list item.

                // Owner name and optional link
                if ($video->getFile()->getOwner()->isDeleted()) {
                    $this->setVar('owner_exists_block', '');
                    $this->parse('owner_deleted_block', 'owner_deleted');
                } else {
                    $this->parse('owner_exists_block', 'owner_exists');
                    $this->setVar('owner_deleted_block', '');
                }

                if ($this->getContentPermission() > UserAccess::ACCESS_READ) {
                    $this->parse('video_edit_block', 'video_edit', true);
                } else {
                    $this->setVar('video_edit_block', '');
                }

                // Video list block
                if ($video->isComplete()) {
                    // Anyone can see completed video uploads if video is not hidden
                    // or they have sufficient permissions
                    $this->parse('video_list_block', 'video_list', true);
                } elseif ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                    // Only users with higher permissions can see pending videos
                    $this->setVar('VIDEO_LIST_STATUS', strtolower($video->getStatus()));

                    $this->parse('video_pending_list_block', 'video_pending_list', true);
                }

                $numVideosListed++;
            }
        }
        if ($numVideosListed === 0 && ($this->getContentPermission() < UserAccess::ACCESS_WRITE)) {
            return false;
        }
        if (is_object($this->defaultVideo) && $this->defaultVideo->isComplete()) {
            $this->setVar('DEFAULT_VIDEO_ID', $this->defaultVideo->getFile()->getId());
            $this->parse('default_video_block', 'default_video');
        }

        if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
            $this->parse('submit_video_block', 'submit_video');
        } else {
            $this->setVar('submit_video_block', '');
        }

        $this->parse('source_folder_exists_block', 'source_folder_exists');

        /* Show empty state when videos existed but none could be shown to the user
         * Hide the list of videos that would otherwise have rendered
         */
        if (!$numVideosListed) {
            $this->setVar('has_video_block', '');
            $this->buildEmptyState();
        } else {
            $this->parse('has_video_block', 'has_video');
        }

        return true; // everything went according to plan
    }
}
