<?php
use Schoolbox\Resource\File\Model;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_AudioController extends Component_Homepage_ControllerAbstract
{
    /**
     * Component name
     *
     * @var string
     */
    protected $componentName = 'Audio';

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
     * @return void
     */
    public function execute()
    {
        $this->allowedFileHandler = new AllowedFile();
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

        $this->setBlock($templateHandle, 'default_audio', 'default_audio_block');
        $this->setBlock($templateHandle, 'audio_list', 'audio_list_block');
        $this->setBlock($templateHandle, 'audio_pending_list', 'audio_pending_list_block');
        $this->setVar(
            array(
                'default_audio_block'      => '',
                'audio_list_block'         => '',
                'audio_pending_list_block' => '',
            )
        );

        // There's not (yet) any default for the audio player.
        // Just use the first in the list
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
            'mime'    => 'audio',
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

        if (count($this->outputList)) {
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
                            'DEFAULT_AUDIO_ID'    => $file->getId(),
                        )
                    );
                    $this->parse('default_audio_block', 'default_audio');
                }
                if (count($this->outputList) > 1) {
                    $output = array(
                        'AUDIO_LIST_ID'        => $file->getId(),
                        'AUDIO_LIST_TITLE'     => Scrub::text($file->getTitle()),
                        'AUDIO_LIST_DURATION'  => $audio->getDuration() >= 3600
                                ? gmdate('H:i:s', $audio->getDuration())
                                : gmdate('i:s', $audio->getDuration()),
                        'AUDIO_LIST_COMMENT'   => Scrub::text($file->getDescription()),
                        'AUDIO_LIST_HIDDEN'    => $file->isHidden() ? 'hidden' : '',
                        'AUDIO_LIST_POSTER_ID' => $file->getOwner()->getId(),
                        'AUDIO_LIST_POSTER'    => $file->getOwner()->getFullName(),
                        'ON_OFF'               => ++$j % 2 ? '' : 'dark'
                    );

                    $this->setVar($output);
                    if ($audio->getStatus() == "completed") {
                        $this->parse('audio_list_block', 'audio_list', true);
                    } elseif ($this->permission >= WRITE_ACCESS) {
                        $this->setVar(
                            array(
                                'AUDIO_LIST_STATUS'    => $audio->getStatus(),
                                'AUDIO_LIST_STATUSIMG' => $audio->getStatusImage('audio'),
                            )
                        );
                        $this->parse('audio_pending_list_block', 'audio_pending_list', true);
                    }
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
