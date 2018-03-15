<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
use Schoolbox\Collection;
use \Schoolbox\Permission\Access\UserAccess;
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
     * @var array
     */
    protected $sortFields = array('name', 'date');

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

        if ($this->getConfig('video')) {
            try {
                $this->defaultVideo = $videoResourceMapper->findByFileId($this->getConfig('video'));
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->defaultVideo = $this->outputList->first();
            }
        } else {
            $this->defaultVideo = $this->outputList->first();
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

        if (count($this->outputList)) {
            $j = 0;

            foreach ($this->outputList as $video) {
                $this->setVar(
                    array(
                        'VIDEO_LIST_ID'        => $video->getFile()->getId(),
                        'VIDEO_LIST_TITLE'     => Scrub::text($video->getFile()->getTitle()),
                        'VIDEO_LIST_DURATION'  => $video->getDuration() >= 3600 ? gmdate('H:i:s', $video->getDuration()) : gmdate('i:s', $video->getDuration()),
                        'VIDEO_LIST_POSTER_ID' => $video->getFile()->getOwner()->getId(),
                        'VIDEO_LIST_POSTER'    => $video->getFile()->getOwner()->getFullName(),
                        'ON_OFF'               => ++$j % 2 ? '' : 'dark'
                    )
                );

                $comment = Scrub::text($video->getFile()->getDescription());

                if ($video->getStatus() === "completed") {
                    if (strlen($comment)) {
                        $this->setVar(array(
                            'VIDEO_LIST_COMMENT' => $comment
                        ));

                        $this->parse('video_list_comment_block', 'video_list_comment');
                    }
                    else {
                        $this->setVar(array(
                            'video_list_comment_block' => ''
                        ));
                    }

                    $this->parse('video_list_block', 'video_list', true);
                } else {
                    if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                        if (strlen($comment)) {
                            $this->setVar(array(
                                'VIDEO_PENDING_COMMENT' => $comment
                            ));

                            $this->parse('video_pending_comment_block', 'video_pending_comment');
                        }
                        else {
                            $this->setVar(array(
                                'video_pending_comment_block' => ''
                            ));
                        }

                        $this->setVar(
                            array(
                                'VIDEO_LIST_STATUS'    => $video->getStatus(),
                                'VIDEO_LIST_STATUSIMG' => $video->getStatusImage(),
                            )
                        );

                        $this->parse('video_pending_list_block', 'video_pending_list', true);
                    }
                }

                // Owner name and optional link
                if($video->getFile()->getOwner()->isDeleted()){
                    $this->setVar('owner_exists_block', '');
                    $this->parse('owner_deleted_block', 'owner_deleted');
                } else {
                    $this->parse('owner_exists_block', 'owner_exists');
                    $this->setVar('owner_deleted_block', '');
                }

                /* Clear children after parsing out the parent
                 */
                $this->setVar(array(
                    'video_list_comment_block' => '',
                    'video_pending_comment_block' => ''
                ));
            }
        }

        if (is_object($this->defaultVideo) && $this->defaultVideo->getStatus() === 'completed') {
            $this->setVar('DEFAULT_VIDEO_ID', $this->defaultVideo->getFile()->getId());
            $this->parse('default_video_block', 'default_video');
        } else {
            $this->setVar('default_video_block', '');
        }

        return true; // everything went according to plan
    }
}
