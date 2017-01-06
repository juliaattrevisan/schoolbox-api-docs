<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Resource\File\Model;
use \Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_AudioController extends Component_Homepage_ControllerAbstract
{

    /**
     * @var AllowedFile
     */
    private $allowedFileHandler;

    /**
     * @var \Schoolbox\Collection|\Schoolbox\Video\ResourceVideo[]
     */
    protected $outputList;

    /**
     * Available sort fields for this component.
     *
     * @var array
     */
    protected $sortFields = array('name', 'date');

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if(!$this->isSourceRenderable()){
            return $this->hasContextWriteAccess();
        }

        $this->allowedFileHandler = new AllowedFile();
        return true;
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'audio')) {
            return false;
        }

        $this->parseConfig();

        $this->setBlock($templateHandle, 'has_audio', 'has_audio_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        // There's not (yet) any default for the audio player.
        // Just use the first in the list
        $videoResourceMapper = new \Schoolbox\Video\ResourceVideoMapper();

        /* Sort order
         */
        $sortConfig = array(
            'sort' => $this->getConfig('sort'),
            'sortdir' => $this->getConfig('sortdir'),
            'mime' => 'audio',
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

        if (!count($this->outputList)) {

            $this->setBlock('empty_state', 'empty_read', 'empty_read_block');
            $this->setBlock('empty_state', 'empty_write', 'empty_write_block');

            $this->setVar([
                'has_audio_block' => '',
                'empty_read_block' => '',
                'empty_write_block' => '',
                'SOURCE_ID' => $this->getSource()->getId(),
            ]);

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->parse('empty_read_block', 'empty_read');
            } else {
                $this->parse('empty_write_block', 'empty_write');
            }

            $this->parse('empty_state_block', 'empty_state');

        } else {

            $this->setBlock('has_audio', 'source_folder_deleted', 'source_folder_deleted_block');
            $this->setBlock('has_audio', 'source_folder_exists', 'source_folder_exists_block');
            $this->setBlock('has_audio', 'empty_state', 'empty_state_block');
            $this->setBlock('source_folder_exists', 'default_audio', 'default_audio_block');
            $this->setBlock('source_folder_exists', 'audio_list', 'audio_list_block');
            $this->setBlock('source_folder_exists', 'audio_pending_list', 'audio_pending_list_block');
            $this->setBlock('audio_list', 'audio_edit', 'audio_edit_block');

            $this->setVar([
                'empty_state_block' => '',
                'default_audio_block' => '',
                'audio_list_block' => '',
                'audio_pending_list_block' => '',
                'source_folder_deleted_block' => '',
            ]);

            if (!$this->isSourceRenderable()) {
                return $this->renderSourceUnavailable();
            }

            $j = 0;
            foreach ($this->outputList as $audio) {

                /**
                 * We can be assured that files here are SBFS files
                 * @var Model $file
                 */
                $file = $audio->getFile();

                if ($j === 0) {
                    // output the default audio, and audio player
                    $this->setVar(
                        array(
                            'DEFAULT_AUDIO_TITLE' => Scrub::text($file->getTitle()),
                            'DEFAULT_AUDIO_ID' => $file->getId(),
                        )
                    );
                    $this->parse('default_audio_block', 'default_audio');
                }

                if (count($this->outputList) >= 1) {

                    $this->setVar([
                        'AUDIO_LIST_ID' => $file->getId(),
                        'AUDIO_LIST_TITLE' => Scrub::text($file->getTitle()),
                        'AUDIO_LIST_DURATION' => $audio->getDuration() >= 3600
                            ? gmdate('H:i:s', $audio->getDuration())
                            : gmdate('i:s', $audio->getDuration()),
                        'AUDIO_LIST_COMMENT' => Scrub::text($file->getDescription()),
                        'AUDIO_LIST_HIDDEN' => $file->isHidden() ? 'hidden' : '',
                        'AUDIO_LIST_POSTER_ID' => $file->getOwner()->getId(),
                        'AUDIO_LIST_POSTER' => $file->getOwner()->getFullName(),
                    ]);

                    $j++;

                    if ($audio->getStatus() == "completed") {

                        if ($this->getContentPermission() > UserAccess::ACCESS_READ) {
                            $this->parse('audio_edit_block', 'audio_edit', true);
                        } else {
                            $this->setVar('audio_edit_block', '');
                        }
                        $this->parse('audio_list_block', 'audio_list', true);

                    } elseif ($this->getContentPermission() >= UserAccess::ACCESS_WRITE) {

                        $this->setVar([
                            'AUDIO_LIST_STATUS' => $audio->getStatus(),
                            'AUDIO_LIST_STATUSIMG' => $audio->getStatusImage('audio'),
                        ]);
                        $this->parse('audio_pending_list_block', 'audio_pending_list', true);
                    }
                }
            }


            //setup block for submission button
            $this->setBlock('source_folder_exists', "submit_file", "submit_file_block");
            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                $this->setVar("submit_file_block", "");
            } else {
                $this->setVar('SOURCE_ID', $this->getSource()->getId());
                $this->parse("submit_file_block", "submit_file");
            }

            $this->parse('source_folder_exists_block','source_folder_exists');
            $this->parse('has_audio_block', 'has_audio');
        }



        return true;
    }


}
