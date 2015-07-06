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
     * Component name
     *
     * @var string
     */
    protected $componentName = 'Video';

    /**
     * @var \Schoolbox\Collection|\Schoolbox\Video\ResourceVideo[]
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
     * @var array
     */
    protected $sortFields = array('name', 'date');

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {
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
        $videoResourceMapper = new \Schoolbox\Video\ResourceVideoMapper();

        $source = $this->getConfig('src');
        if (empty($source)) {
            $source = $this->getContext()->getId();
        }

        /* Sort order
         */
        $sortConfig = array(
            'sort'    => $this->getConfig('sort'),
            'sortdir' => $this->getConfig('sortdir'),
            'mime'    => 'video',
        );

        $this->source_category_id = $source;

        if ($this->getConfig('size') >= 1) {
            $this->outputList = $videoResourceMapper->findByCategory(
                    $source,
                    $sortConfig,
                    $this->getConfig('size')
            );
        } elseif ($this->getConfig('size') == 0) {
            $this->outputList = $videoResourceMapper->findByCategory($source, $sortConfig);
        } else {
            $this->outputList = new \Schoolbox\Collection(array());
        }
        // Select a video to play by default.
        // Only show a video that has actually finished encoding, though.
        $this->defaultVideo = null;
        if ($this->getConfig('video')) {
            try {
                $video = $videoResourceMapper->findByFileId($this->getConfig('video'));
                if ($video->isComplete() && $video->getFile()->getFolder()->getId() == $source) {
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

        if ($this->permission < READ_ACCESS) {
            return false;
        }

        // Set up component name
        $this->setVar(array(
            'FILE_CAT_ID' => $this->source_category_id,
        ));

        $this->setComponentName(strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname'): $this->getComponentName());

        $this->setBlock($templateHandle, 'default_video', 'default_video_block');
        $this->setBlock($templateHandle, 'video_list', 'video_list_block');
        $this->setBlock($templateHandle, 'video_pending_list', 'video_pending_list_block');
        $this->setBlock($templateHandle, 'submit_video', 'submit_video_block');

        $this->setVar('video_list_block', '');
        $this->setVar('video_pending_list_block', '');
        $this->setVar('default_video_block', '');

        if ($this->getConfig('size') != 1 && count($this->outputList)) {
            $j = 0;
            foreach ($this->outputList as $video) {
                $this->setVar(
                    array(
                        'VIDEO_LIST_ID'        => $video->getFile()->getId(),
                        'VIDEO_LIST_TITLE'     => Scrub::text($video->getFile()->getTitle()),
                        'VIDEO_LIST_DURATION'  => $video->getDuration() >= 3600
                            ? gmdate('H:i:s', $video->getDuration())
                            : gmdate('i:s', $video->getDuration()),
                        'VIDEO_LIST_COMMENT'   => Scrub::text($video->getFile()->getDescription()),
                        'VIDEO_LIST_HIDDEN'    => $video->getFile()->getHidden() ? 'hidden' : '',
                        'VIDEO_LIST_POSTER_ID' => $video->getFile()->getOwner()->getId(),
                        'VIDEO_LIST_POSTER'    => $video->getFile()->getOwner()->getFullName(),
                        'ON_OFF'               => ++$j % 2 ? '' : 'dark'
                    )
                );
                if ($video->getStatus() == "completed") {
                    $this->parse('video_list_block', 'video_list', true);
                } elseif ($this->permission >= WRITE_ACCESS) {
                    $this->setVar(
                        array(
                            'VIDEO_LIST_STATUS'    => $video->getStatus(),
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

        if ($this->permission >= WRITE_ACCESS) {
            $this->parse('submit_video_block', 'submit_video');
        } else {
            $this->setVar('submit_video_block', '');
        }

        /* Need title injected here
         */
        $this->parseConfig();

        return true; // everything went according to plan
    }
}
