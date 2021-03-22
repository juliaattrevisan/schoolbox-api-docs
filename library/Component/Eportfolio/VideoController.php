<?php
/**
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Cdn\PrefixGenerator;
use \Schoolbox\Permission\Access\UserAccess;

/**
 * @method \Schoolbox\Video\ResourceVideoMapper getMapper()
 */
class Component_Eportfolio_VideoController extends Component_Eportfolio_ControllerAbstract
{
    /**
    * @var \Schoolbox\Collection
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
     * @return bool
     */
    public function execute()
    {
        $this->setMapper($this->getMapperFactory()->get(\Schoolbox\Video\ResourceVideoMapper::class));
        return true;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'Video')) {
            return false;
        }

        $this->parseConfig();

        $pageId = $this->getInstance()->getIdentifier();

        /* Sort order
		 */
        $sortConfig = array(
            'sort' => $this->getConfig('sort'),
            'sortdir' => $this->getConfig('sortdir')
        );


        if ($this->getConfig('size') > 1) {
            $this->outputList = $this->getMapper()->findByEportfolioPage($pageId, $sortConfig, $this->getConfig('size')-1);
        } elseif ($this->getConfig('size') == 0) {
            $this->outputList = $this->getMapper()->findByEportfolioPage($pageId, $sortConfig);
        } else {
            $this->outputList = new \Schoolbox\Collection();
        }

        if ($this->getConfig('eportfolioVideo')) {
            try {
                $this->defaultVideo = $this->getMapper()->findByMyFileId($this->getConfig('eportfolioVideo'));
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->defaultVideo = $this->outputList->first();
            }
        } else {
            $this->defaultVideo = $this->outputList->first();
        }

        // Set up component name
        $this->setVar(array(
            'COMPONENT_NAME' => strlen($this->getConfig('componentname')) ? $this->getConfig('componentname') : 'Video',
        ));

        $this->setBlock($templateHandle, 'default_video', 'default_video_block');
        $this->setBlock($templateHandle, 'video_list', 'video_list_block');
        $this->setBlock($templateHandle, 'video_pending_list', 'video_pending_list_block');
        $this->setBlock($templateHandle, 'submit_video', 'submit_video_block');
        $this->setBlock($templateHandle, 'dropzone', 'dropzone_block');

        $this->setVar('video_list_block', '');
        $this->setVar('video_pending_list_block', '');
        $this->setVar('default_video_block', '');

        $prefixGenerator = new PrefixGenerator($this->getConfigLoader());
        $cdnPrefix       = $prefixGenerator->generate();

        if (count($this->outputList)) {
            $j = 0;

            foreach ($this->outputList as $video) {
                $this->setVar(array(
                    'VIDEO_LIST_ID' => $video->getFile()->getId(),
                    'VIDEO_LIST_TITLE' => Scrub::text($video->getFile()->getTitle()),
                    'VIDEO_LIST_DURATION' => $video->getDuration() >= 3600 ? gmdate('H:i:s', $video->getDuration()) : gmdate('i:s', $video->getDuration()),
                    'VIDEO_LIST_COMMENT' => Scrub::text($video->getFile()->getTitle()),
                    'VIDEO_LIST_POSTER_ID' => $video->getFile()->getOwner()->getId(),
                    'VIDEO_LIST_POSTER' => $video->getFile()->getOwner()->getFullName(),
                    'ON_OFF' => ++$j % 2 ? '' : 'dark'
                ));

                if ($video->getStatus() == "completed") {
                    $this->parse('video_list_block', 'video_list', true);
                }                 elseif ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
                    $this->setVar(array(
                        'VIDEO_LIST_STATUS'    => $video->getStatus(),
                        'VIDEO_LIST_STATUSIMG' => $cdnPrefix . $video->getStatusImage(),
                    ));

                    $this->parse('video_pending_list_block', 'video_pending_list', true);
                }
            }
        }

        if (is_object($this->defaultVideo) && $this->defaultVideo->getStatus() == 'completed') {
            $this->setVar('DEFAULT_VIDEO_ID', $this->defaultVideo->getFile()->getId());
            $this->parse('default_video_block', 'default_video');
        } else {
            $this->setVar('default_video_block', '');
        }

        if ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {
            $this->parse('dropzone_block', 'dropzone');
            $this->parse('submit_video_block', 'submit_video');
        } else {
            $this->setVar('dropzone_block', '');
            $this->setVar('submit_video_block', '');
        }

        return true; // everything went according to plan
    }
}
