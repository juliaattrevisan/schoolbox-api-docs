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
        ));

	    $this->setBlock($templateHandle, 'default_video', 'default_video_block');
	    $this->setBlock($templateHandle, 'video_list', 'video_list_block');

	    if(is_object($this->defaultVideo)) {
	        $this->setVar('DEFAULT_VIDEO_ID', $this->defaultVideo->getFile()->getId());
	        $this->parse('default_video_block','default_video');
        }
	    else {
		    $this->setVar('default_video_block','');
	    }
	    if(count($this->outputList)) {



		    foreach($this->outputList as $video ) {
			    $this->setVar( array('VIDEO_LIST_ID'     => $video->getFile()->getId(),
				                     'VIDEO_LIST_TITLE'  => Scrub::text($video->getFile()->getDescription()),
				                     'VIDEO_LIST_DURATION'=> gmdate('H:i:s',$video->getDuration()),
				                     'VIDEO_LIST_COMMENT' => Scrub::text($video->getFile()->getComment()),
				                     'VIDEO_LIST_HIDDEN' => $video->getFile()->getHidden() ? 'hidden' : '',
				                     //'VIDEO_LIST_POSTER' => $video->getFile()->getPoster()->getHTMLName();
			                   ));
				$this->parse('video_list_block','video_list',true);
		    }
	    }
	    else {
		    $this->setVar('video_list_block','');
	    }

        return true; // everything went according to plan

    }

}
