<?php
/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
use \Schoolbox\Permission\Access\UserAccess;
use \Schoolbox\Video\ResourceVideoMapper;

class Component_Homepage_VideoController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var \Schoolbox\Collection|\Schoolbox\Video\ResourceVideo[]
     */
    protected $outputList;

    /**
     * @var \Schoolbox\Video\ResourceVideo
     */
    protected $defaultVideo;

    /**
     * Available sort fields for this component.
     *
     * @var array
     */
    protected $sortFields = array('name', 'date');

    /**
     * Determine whether the current user should be able to see the video given as an argument.
     *
     * @param \Schoolbox\Video\ResourceVideo $video
     *
     * @return boolean
     */
    private function isVisibleToCurrentUser($video)
    {
        // Read access users should not be able to see hidden videos.
        return !$video->getFile()->getHidden() || $this->getContentPermission() > UserAccess::ACCESS_READ;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     *
     * @param String $templateHandle
     *
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'video')) {
            return false;
        }

        /* Need title injected here
         */
        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_video', 'has_video_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        $videoResourceMapper = ResourceVideoMapper::getInstance();

        /* Sort order
         */
        $sortConfig = array(
            'sort' => $this->getConfig('sort'),
            'sortdir' => $this->getConfig('sortdir'),
            'mime' => 'video',
        );

        if ($this->getConfig('size') >= 1) {
            $this->outputList = $videoResourceMapper->findByCategory(
                $this->getSource()->getId(),
                $sortConfig,
                $this->getConfig('size')
            );
        } elseif ($this->getConfig('size') == 0) {
            $this->outputList = $videoResourceMapper->findByCategory($this->getSource()->getId(), $sortConfig);
        } else {
            $this->outputList = new \Schoolbox\Collection(array());
        }

        // Set up component name
        $this->setVar([
            'FILE_CAT_ID' => $this->getSource()->getId(),
        ]);

        if ($this->outputList->count() == 0) {

            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
            $this->setBlock('empty_state', 'empty_write', 'empty_write_block');

            $this->setVar([
                'has_video_block' => '',
                'empty_read_block' => '',
                'empty_write_block' => '',
                'SOURCE_ID' => '',
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_block', 'empty_read');
            } else {
                $this->parse('empty_write_block', 'empty_write');
            }

            $this->parse('empty_state_block', 'empty_state');            
            
        } else {

            $this->setBlock('has_video', 'source_folder_deleted', 'source_folder_deleted_block');
            $this->setBlock('has_video', 'source_folder_exists', 'source_folder_exists_block');
            $this->setBlock('source_folder_exists', 'default_video', 'default_video_block');
            $this->setBlock('source_folder_exists', 'video_list', 'video_list_block');
            $this->setBlock('source_folder_exists', 'video_pending_list', 'video_pending_list_block');
            $this->setBlock('source_folder_exists', 'submit_video', 'submit_video_block');
            $this->setBlock('video_list', 'owner_exists', 'owner_exists_block');
            $this->setBlock('video_list', 'owner_deleted', 'owner_deleted_block');

            $this->setVar('empty_state_block', '');

            if (!$this->isSourceRenderable()) {
                return $this->renderSourceUnavailable();
            } else {
                $this->setVar('source_folder_deleted_block', '');

                // Select a video to play by default.
                // Only show a video that has actually finished encoding, though.
                $this->defaultVideo = null;
                if ($this->getConfig('video')) {
                    try {
                        $video = $videoResourceMapper->findByFileId($this->getConfig('video'));
                        if ($video->isComplete() && $video->getFile()->getFolder()->getId() == $this->getSource()->getId()) {
                            $this->defaultVideo = $video;
                        }
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        // passthru: figure out which video later
                    }
                }
                if (!isset($this->defaultVideo)) {
                    // Show the first video that has completed encoding
                    foreach ($this->outputList as $video) {
                        /** @var \Schoolbox\Video\ResourceVideo $video */
                        // Don't pick a video not visible to the user as the default video
                        if ($video->isComplete() && $this->isVisibleToCurrentUser($video)) {
                            $this->defaultVideo = $video;
                            break;
                        }
                    }
                }

                if ($this->getContentPermission() < UserAccess::ACCESS_READ) {
                    return false;
                }

                $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());


                $this->setVar([
                    'video_list_block' => '',
                    'video_pending_list_block' => '',
                    'default_video_block' => '',
                ]);

                $this->setBlock('video_list', 'video_edit', 'video_edit_block');

                if (count($this->outputList) >= 1) {
                    foreach ($this->outputList as $video) {
                        $this->setVar([
                            'VIDEO_LIST_ID' =>          $video->getFile()->getId(),
                            'VIDEO_LIST_TITLE' =>       Scrub::text($video->getFile()->getTitle()),
                            'VIDEO_LIST_DURATION' =>    $video->getDuration() >= 3600
                                                        ? gmdate('H:i:s', $video->getDuration())
                                                        : gmdate('i:s', $video->getDuration()),
                            'VIDEO_LIST_COMMENT' =>     Scrub::text($video->getFile()->getDescription()),
                            'VIDEO_LIST_HIDDEN' =>      $video->getFile()->getHidden() ? 'hidden' : '',
                            'VIDEO_LIST_POSTER_ID' =>   $video->getFile()->getOwner()->getId(),
                            'VIDEO_LIST_POSTER' =>      $video->getFile()->getOwner()->getFullName(),
                        ]);

                        // Display the list of videos if the user has permission to view them.
                        if ($this->isVisibleToCurrentUser($video)) {
                            // Owner and edit blocks are children of the video list item,
                            // so should not appear unless we are parsing the list item.

                            // Owner name and optional link
                            if($video->getFile()->getOwner()->isDeleted()){
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
                            if ($video->getStatus() == "completed") {
                                // Anyone can see completed video uploads if video is not hidden
                                // or they have sufficient permissions
                                $this->parse('video_list_block', 'video_list', true);
                            } elseif ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                                // Only users with higher permissions can see pending videos
                                $this->setVar([
                                    'VIDEO_LIST_STATUS' => $video->getStatus(),
                                    'VIDEO_LIST_STATUSIMG' => $video->getStatusImage(),
                                ]);
                                $this->parse('video_pending_list_block', 'video_pending_list', true);
                            }
                        }

                    }
                }

                if (is_object($this->defaultVideo) and $this->defaultVideo->getStatus() == 'completed') {
                    $this->setVar('DEFAULT_VIDEO_ID', $this->defaultVideo->getFile()->getId());
                    $this->parse('default_video_block', 'default_video');
                }

                if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                    $this->parse('submit_video_block', 'submit_video');
                } else {
                    $this->setVar('submit_video_block', '');
                }
                $this->parse('source_folder_exists_block', 'source_folder_exists');
            }

            $this->parse('has_video_block', 'has_video');
        }

        return true; // everything went according to plan
    }
}