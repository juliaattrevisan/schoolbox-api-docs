<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Component_Homepage_VideoController extends Component_Homepage_ControllerAbstract {

	/**
	 * @var \Schoolbox\Collection
	 */
	protected $outputList;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
	    $videoResourceMapper = new \Schoolbox\Video\ResourceVideoMapper();



		if($this->getConfig('size') > 1)
	        $this->outputList = $videoResourceMapper->findByCategory($this->getContext('id'), $this->getConfig('size')-1);
		else if($this->getConfig('size') == 0)
			$this->outputList = $videoResourceMapper->findByCategory($this->getContext('id'));
		else
			$this->outputList = new \Schoolbox\Collection(array());

	    if($this->getConfig('video'))
	        $this->defaultVideo = $videoResourceMapper->findById($this->getConfig('video'));
	    else
		    $this->defaultVideo = $this->outputList->current();

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'video')) {
            return false;
        }
        $this->parseConfig();

        // Set up component name
        $this->setVar(array(
            'COMPONENT_NAME' => strlen(trim($this->getConfig('componentname'))) ? $this->getConfig('componentname') : 'Video',
	        'FILE_CAT_ID'   => $this->getContext('id'),
        ));

	    $this->setBlock($templateHandle, 'default_video', 'default_video_block');
	    $this->setBlock($templateHandle, 'video_list', 'video_list_block');
	    $this->setBlock($templateHandle, 'video_pending_list', 'video_pending_list_block');

	    if(count($this->outputList)) {
			$j = 0;
		$totalcompleted = 0;
		$totalpending = 0;
		    foreach($this->outputList as $video ) {
			if($video->getStatus() == "completed"){
				$this->setVar( array('VIDEO_LIST_ID'     => $video->getFile()->getId(),
				                     'VIDEO_LIST_TITLE'  => Scrub::text($video->getFile()->getDescription()),
				                     'VIDEO_LIST_DURATION'=> $video->getDuration()>=3600?gmdate('H:i:s',$video->getDuration()):gmdate('i:s',$video->getDuration()),
				                     'VIDEO_LIST_COMMENT' => Scrub::text($video->getFile()->getComment()),
				                     'VIDEO_LIST_HIDDEN' => $video->getFile()->getHidden() ? 'hidden' : '',
				                     'VIDEO_LIST_POSTER_ID'=> $video->getFile()->getOwner()->getId(),
				                     'VIDEO_LIST_POSTER' => $video->getFile()->getOwner()->getFullName(),
									 'ON_OFF' => ++$j % 2 ? '' : 'dark'
			                   ));
				$this->parse('video_list_block','video_list',true);
				$totalcompleted++;
			}
		    }
		}
		if($this->permission < WRITE_ACCESS) {
		    $totalpending = 0;
		}
		else{
		    foreach($this->outputList as $video ) {
			if($video->getStatus() != "completed"){
				$this->setVar( array('VIDEO_LIST_ID'     => $video->getFile()->getId(),
						    'VIDEO_LIST_TITLE'  => Scrub::text($video->getFile()->getDescription()),
						    'VIDEO_LIST_DURATION'=> $video->getDuration()>=3600?gmdate('H:i:s',$video->getDuration()):gmdate('i:s',$video->getDuration()),
						    'VIDEO_LIST_COMMENT' => Scrub::text($video->getFile()->getComment()),
						    'VIDEO_LIST_HIDDEN' => $video->getFile()->getHidden() ? 'hidden' : '',
						    'VIDEO_LIST_POSTER_ID'=> $video->getFile()->getOwner()->getId(),
						    'VIDEO_LIST_POSTER' => $video->getFile()->getOwner()->getFullName(),
						    'VIDEO_LIST_STATUS' => $video->getStatus(),
						    'VIDEO_LIST_STATUSIMG' => "/images/core/component/video-thumbnail-".strtolower($video->getStatus()).".jpg",
				));
				$this->parse('video_pending_list_block','video_pending_list',true);
				$totalpending++;
			}
		    }
		}
		    
		    if($totalcompleted == 0){ $this->setVar('video_list_block',''); }
		    if($totalpending == 0){ $this->setVar('video_pending_list_block',''); }
	    }
	    else {
		    $this->setVar('video_list_block','');
		    $this->setVar('video_pending_list_block','');
	    }


	    if(is_object($this->defaultVideo) AND $totalcompleted != 0) {
		$this->setVar('DEFAULT_VIDEO_ID', $this->defaultVideo->getFile()->getId());
		$this->parse('default_video_block','default_video');
	    }
	    else {
		$this->setVar('default_video_block','');
	    }

        return true; // everything went according to plan

    }

}
