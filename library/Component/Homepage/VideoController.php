<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Component_Homepage_VideoController extends Component_Homepage_ControllerAbstract {


    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

	    $fileHandler = new File;
	    $fileList = $fileHandler->getAllInCategory($this->getContext('id'),
		    array(
	        //'size' => $this->getConfig('size'),
            'filter' => 'video',
            //'sort'  => $this->getConfig('sort'),
            //'sortdir' => $this->getConfig('sortdir'
		    ));

	    $this->outputList = array();
	    if(is_array($fileList)) {

		    foreach($fileList as $file) {
			    if(is_readable(Cfg::get('video_dir').'/'.$file['id'].'.mp4')) {
				    $this->outputList[] = $file;
			    }
		    }
	    }


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


	    if(count($this->outputList)) {

		    $this->setVar('DEFAULT_VIDEO_ID', $this->outputList[0]['id']);
		    $this->parse('default_video_block','default_video');

		    foreach($this->outputList as $video ) {
			    $this->setVar( array('VIDEO_LIST_ID'     => $video['id'],
				                     'VIDEO_LIST_TITLE'  => Scrub::text($video['description']),
				                     'VIDEO_LIST_HIDDEN' => $video['hidden'] ? 'hidden' : '',
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
