<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
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

        $this->setBlock($templateHandle, 'source_folder_deleted', 'source_folder_deleted_block');
        $this->setBlock($templateHandle, 'source_folder_exists', 'source_folder_exists_block');
        $this->setBlock('source_folder_exists', 'default_video', 'default_video_block');
        $this->setBlock('source_folder_exists', 'video_list', 'video_list_block');
        $this->setBlock('source_folder_exists', 'video_pending_list', 'video_pending_list_block');
        $this->setBlock('source_folder_exists', 'submit_video', 'submit_video_block');

        if(!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailable();
        } else {
            $this->setVar('source_folder_deleted_block', '');

            $videoResourceMapper = \Schoolbox\Video\ResourceVideoMapper::getInstance();

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
                    if ($video->isComplete()) {
                        $this->defaultVideo = $video;
                        break;
                    }
                }
            }

            if ($this->getContentPermission() < READ_ACCESS) {
                return false;
            }

            // Set up component name
            $this->setVar(array(
                'FILE_CAT_ID' => $this->getSource()->getId(),
            ));

            $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : $this->getComponentName());


            $this->setVar('video_list_block', '');
            $this->setVar('video_pending_list_block', '');
            $this->setVar('default_video_block', '');

            $this->setBlock('video_list', 'video_edit', 'video_edit_block');

            if (count($this->outputList) >= 1) {
                $j = 0;
                foreach ($this->outputList as $video) {
                    $this->setVar(
                        array(
                            'VIDEO_LIST_ID' => $video->getFile()->getId(),
                            'VIDEO_LIST_TITLE' => Scrub::text($video->getFile()->getTitle()),
                            'VIDEO_LIST_DURATION' => $video->getDuration() >= 3600
                                ? gmdate('H:i:s', $video->getDuration())
                                : gmdate('i:s', $video->getDuration()),
                            'VIDEO_LIST_COMMENT' => Scrub::text($video->getFile()->getDescription()),
                            'VIDEO_LIST_HIDDEN' => $video->getFile()->getHidden() ? 'hidden' : '',
                            'VIDEO_LIST_POSTER_ID' => $video->getFile()->getOwner()->getId(),
                            'VIDEO_LIST_POSTER' => $video->getFile()->getOwner()->getFullName(),
                            'ON_OFF' => ++$j % 2 ? '' : 'dark'
                        )
                    );

                    if ($this->getContentPermission() > READ_ACCESS) {
                        $this->parse('video_edit_block', 'video_edit', true);
                    } else {
                        $this->setVar('video_edit_block', '');
                    }

                    if ($video->getStatus() == "completed") {
                        $this->parse('video_list_block', 'video_list', true);
                    } elseif ($this->getContentPermission() >= WRITE_ACCESS) {
                        $this->setVar(
                            array(
                                'VIDEO_LIST_STATUS' => $video->getStatus(),
                                'VIDEO_LIST_STATUSIMG' => $video->getStatusImage(),
                            )
                        );
                        $this->parse('video_pending_list_block', 'video_pending_list', true);
                    }
                }
            }

            if (is_object($this->defaultVideo) and $this->defaultVideo->getStatus() == 'completed') {
                $this->setVar('DEFAULT_VIDEO_ID', $this->defaultVideo->getFile()->getId());
                $this->parse('default_video_block', 'default_video');
            } else {
                $this->setVar('default_video_block', '');
            }

            if ($this->getContentPermission() >= WRITE_ACCESS) {
                $this->parse('submit_video_block', 'submit_video');
            } else {
                $this->setVar('submit_video_block', '');
            }
            $this->parse('source_folder_exists_block','source_folder_exists');
        }

        /* Need title injected here
         */
        $this->parseConfig();

        return true; // everything went according to plan
    }
}
