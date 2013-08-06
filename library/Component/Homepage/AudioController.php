<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_AudioController extends Component_Homepage_ControllerAbstract {

    /**
     * @var AllowedFile
     */
    private $allowedFileHandler;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        $this->allowedFileHandler = new AllowedFile();
        $this->sortFields = array('Date');
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'audio')) {
            return false;
        }
        $this->parseConfig();

        $this->setJavascript('/javascript/swfobject.js');
        $this->setBlock($templateHandle, 'audio_list', 'audio_list_block');

        $fileHandler = new File;

        //
        // WAV: no longer supported because Flash no longer supports WAV
        // playback. Long term fix - add a transcoder.
        $config['filter'] = array(
            'audio/mpeg', 'audio/mpa',
            'audio/aac', 'audio/mp4',
//            'audio/wav', 'audio/wave', 'audio/x-wav'
        );

        $fileList = $fileHandler->getAllInCategory($this->getContext('id'), $config);
        if (is_array($fileList)) {
            $j = 0;
            foreach ($fileList as $file) {
                $output = array('AUDIO_MIME_IMAGE'	=> $this->allowedFileHandler->getClass($file['iconFile']),
                    'AUDIO_URL'			=> '/send.php?id=' . $file['id'],
                    'AUDIO_DESCRIPTION'	=> Scrub::text($file['description']),
                    'AUDIO_COMMENT'		=> Scrub::text($file['comment']),
                    'AUDIO_ID'			=> $file['id'],
                    'ON_OFF'			=> ++$j % 2 ? '' : 'dark',
                );

                $this->setVar($output);
                $this->parse('audio_list_block', 'audio_list', true);
            }
        } else
            $this->setVar('audio_list_block', '');

        if (!empty($config['url']))
            $this->setVar('AUDIO_URL', !empty($config['url']) ? $config['url'] : '/homepage/playlist.php?categoryID=' . $this->getContext('id'));

        //setup block for submission button
        $this->setBlock($templateHandle, "submit_file", "submit_file_block");
        if ($this->permission < WRITE_ACCESS)
            $this->setVar("submit_file_block", "");
        else
            $this->parse("submit_file_block", "submit_file");



        return true;
    }

}
