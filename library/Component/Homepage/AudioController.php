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
        $this->sortFields = array('name', 'date');
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

        $this->setBlock($templateHandle, 'audio_list', 'audio_list_block');
        $this->setBlock($templateHandle, 'audio_pending_list', 'audio_pending_list_block');
        $this->setVar(
            array(
                'audio_list_block'         => '',
                'audio_pending_list_block' => '',
            )
        );

        $videoResourceMapper = new \Schoolbox\Video\ResourceVideoMapper();

        $source = $this->getConfig('src');
        if (empty($source)) {
            $source = $this->getContext('id');
        }

        /* Sort order
         */
        $sortConfig = array(
            'sort'    => $this->getConfig('sort'),
            'sortdir' => $this->getConfig('sortdir'),
            'mime'    => 'audio',
        );

        $this->source_category_id = $source;

        if ($this->getConfig('size') > 1) {
            $this->outputList = $videoResourceMapper->findByCategory(
                $source,
                $sortConfig,
                $this->getConfig('size') - 1
            );
        } elseif ($this->getConfig('size') == 0) {
            $this->outputList = $videoResourceMapper->findByCategory($source, $sortConfig);
        } else {
            $this->outputList = new \Schoolbox\Collection(array());
        }

        if (count($this->outputList)) {
            $j = 0;
            foreach ($this->outputList as $file) {
                $output = array(
                    'AUDIO_MIME_IMAGE'	=> '',
                    'AUDIO_URL'			=> '/send.php?id=' .$file->getFile()->getId(),
                    'AUDIO_DESCRIPTION'	=> Scrub::text($file->getFile()->getDescription()),
                    'AUDIO_COMMENT'		=> Scrub::text($file->getFile()->getComment()),
                    'AUDIO_ID'			=> $file->getFile()->getId(),
                    'ON_OFF'			=> ++$j % 2 ? '' : 'dark',
                );

                $this->setVar($output);
                if ($file->getStatus() == "completed") {
                    $this->parse('audio_list_block', 'audio_list', true);
                } elseif ($this->permission >= WRITE_ACCESS) {
                    $this->setVar(
                        array(
                            'AUDIO_LIST_STATUS'    => $file->getStatus(),
                            'AUDIO_LIST_STATUSIMG' => $file->getStatusImage(),
                        )
                    );
                    $this->parse('audio_pending_list_block', 'audio_pending_list', true);
                }
            }
        } else {
            $this->setVar('audio_list_block', '');
        }

        //setup block for submission button
        $this->setBlock($templateHandle, "submit_file", "submit_file_block");
        if ($this->permission < WRITE_ACCESS) {
            $this->setVar("submit_file_block", "");
        } else {
            $this->parse("submit_file_block", "submit_file");
        }



        return true;
    }

}
