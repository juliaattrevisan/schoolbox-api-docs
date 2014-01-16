<?php
/**
 * None
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Crocodoc\Renderer\Strategy;

class Video extends Base {

	/**
	 * The template file to inject
	 *
	 * @var string
	 */
	protected $templateFile = 'submission/inlineGrading/injectables/views/video.html';

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
		$this->getPage()->setBlock('INJECTED_INTERFACE_TEMPLATE', 'injectable_video_player', 'injectable_video_player_block');
		$this->getPage()->setBlock('INJECTED_INTERFACE_TEMPLATE', 'injectable_video_status', 'injectable_video_status_block');

		try {
			$videoMapper = new \Schoolbox\Video\Factory\Video('submission', $this->getSubmission()->getId());
			$video = $videoMapper->produce();
		}
		catch (\Exception $e) {
		}

		/* If video is finished processing, show it in a player
		 * Otherwise, if no video, then render a thumbnail instead
		 */
		if ($video->isComplete()) {
			$this->getPage()->parse('injectable_video_player_block', 'injectable_video_player');
			$this->getPage()->setVar(array(
				'VIDEO_ID' => $video->getFile()->getId(),
				'injectable_video_status_block' => ''
			));
		}
		else {
			$this->getPage()->setVar(array(
				'INJECTABLE_VIDEO_STATUS_SRC' => $video->getStatusImage(),
				'injectable_video_player_block' => ''
			));

			$this->getPage()->parse('injectable_video_status_block', 'injectable_video_status');
		}

		/* Upload file
		 */
		$this->getPage()->setFile('injectableUpload', $this->getUploadFile());
		$this->getPage()->parse('INJECTED_MARK_TEMPLATE', 'injectableUpload');

		/* Control how Return Work is done
		 */
		$this->getPage()->setVar(array(
			'INJECTED_INTERFACE_CONTROL' => ''
		));
	}
}