<?php
/**
 * Audio
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Crocodoc\Renderer\Strategy;

class Audio extends Base {

	/**
	 * The template file to inject
	 *
	 * @var string
	 */
	protected $templateFile = 'submission/inlineGrading/injectables/views/audio.html';

	/**
	 * Control file, to determine how to return work
	 *
	 * @var string
	 */
	protected $controlFile = 'submission/inlineGrading/injectables/controls/redirect-return-control.html';

	/**
	 * Render injectable
	 *
	 * @return mixed|void
	 */
	public function render() {
		$this->getPage()->setFile('injectable', $this->getTemplateFile());
		$this->getPage()->parse('INJECTED_INTERFACE_TEMPLATE', 'injectable');

		/* Set up blocks and stuff
		 */
		$this->getPage()->setBlock('INJECTED_INTERFACE_TEMPLATE', 'injectable_audio_player', 'injectable_audio_player_block');
		$this->getPage()->setBlock('INJECTED_INTERFACE_TEMPLATE', 'injectable_audio_status', 'injectable_audio_status_block');

        /* Upload file
        */
        $this->getPage()->setFile('injectableUpload', $this->getUploadFile());
        $this->getPage()->parse('INJECTED_MARK_TEMPLATE', 'injectableUpload');

        /* Control how Return Work is done
         */
        $this->getPage()->setVar(array(
            'INJECTED_INTERFACE_CONTROL' => ''
        ));

		try {
            $audioMapper = new \Schoolbox\Video\ResourceVideoMapper();
            $audio = $audioMapper->findByStorageHash($this->getSubmission()->getHash());
		}
		catch (\Exception $e) {
            $this->getPage()->setVar(array(
                'INJECTABLE_AUDIO_STATUS_SRC' => '/images/core/corrupt-image.png',
                'INJECTABLE_AUDIO_STATUS_MSG' => 'Sorry we are unable to process this audio, please download the audio to watch.',
                'injectable_audio_player_block' => ''
            ));

            $this->getPage()->parse('injectable_audio_status_block', 'injectable_audio_status');

            return;
        }

		/* If audio is finished processing, show it in a player
		 * Otherwise, if no audio, then render a thumbnail instead
		 */
		if ($audio->isComplete()) {
			$this->getPage()->parse('injectable_audio_player_block', 'injectable_audio_player');
			$this->getPage()->setVar(array(
				'AUDIO_ID' => $this->getSubmission()->getId(),
				'injectable_audio_status_block' => ''
			));
		}
		else {
			$this->getPage()->setVar(array(
                'INJECTABLE_AUDIO_STATUS_MSG' => 'We are currently processing this audio, if you return later it will be ready to play.',
				'injectable_audio_player_block' => ''
			));

			$this->getPage()->parse('injectable_audio_status_block', 'injectable_audio_status');
		}

		/* Upload File
		 */
		$this->parseInjectableUpload();
	}
}