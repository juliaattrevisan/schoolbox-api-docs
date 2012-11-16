<?php
/**
 * Eportfolio_Image_Feature
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Image_Feature extends Eportfolio_Image_Abstract {

	/**
	 * Set up blocks and stuff
	 *
	 * @return void
	 */
	public function setup() {
		$this->getPage()->setBlock($this->getComponentHandle(), 'previewimage', 'preview_block');

		$this->setDefaultConfig();
	}

	/**
	 * Set up default config values, or modify them
	 * prior to their actual usage
	 *
	 * @return void
	 */
	public function setDefaultConfig() {
		$config = $this->getConfig();

		if (empty($config['pixel'])) {
			$config['pixel'] = 222;
		}

		if ($config['pixel'] > 600){
			$config['pixel'] = 600;
		}

		if ($config['pixel'] < 20) {
			$config['pixel'] = 20;
		}

		$this->setConfig($config);
	}

	/**
	 * Find out whether a local or external url was intended
	 *
	 * @return bool
	 */
	private function isImageUrl() {
		$config = $this->getConfig();

		if ($config['url'] != '#' && strlen($config['url'])) {
			return true;
		}

		return false;
	}

	/**
	 * Render image
	 *
	 * @return void
	 */
	public function render() {
		$this->setup();

		if ($this->isImageUrl()) {
			$this->renderUrl();
		}
		else {
			$this->renderLocal();
		}
	}

	/**
	 * Render a url that was specified
	 *
	 * @return void
	 */
	private function renderUrl() {
		$config = $this->getConfig();

		$url = new Url($config['url']);

		$this->getPage()->setVar('PREVIEW_IMAGE_URL', $config['url']);
		$this->getPage()->setVar('PREVIEW_IMAGE_DESC', Scrub::text($config['url']));
		$this->getPage()->setVar('PREVIEW_IMAGE_PIXELS', Scrub::text($config['pixel']));

		if ($url->isUrl()) {
			$this->getPage()->setVar('CONTENT_URL_START', "<a href=\"" . $url->getBase64EncodedRedirect() . "\" target=\"_blank\">");
			$this->getPage()->setVar('CONTENT_URL_END', '</a>');
		}
		else {
			$this->getPage()->setVar('CONTENT_URL_START', '');
			$this->getPage()->setVar('CONTENT_URL_END', '');
		}

		$this->getPage()->parse('preview_block', 'previewimage');
	}

	/**
	 * Get the chosen image (uploaded locally)
	 *
	 * @return array|bool
	 */
	private function getChosenImage() {
		$config = $this->getConfig();

		$query = "SELECT id, description FROM myFile WHERE owner = '{$this->getUserId()}' AND deleted = '0' AND description LIKE '{$config['image']}'";

		return $this->db->getRow($query);
	}

	/**
	 * Get a default image (uploaded locally)
	 *
	 * @return void
	 */
	private function getDefaultImage() {
		$query = "SELECT id, description FROM myFile WHERE owner = '{$this->getUserId()}' AND deleted = '0' AND INSTR(mime_type, 'image/')";

		return $this->db->getRow($query);
	}

	/**
	 * Render a file that was uploaded
	 *
	 * @return void
	 */
	private function renderLocal() {
		$config = $this->getConfig();
		$continue = true;

		$image = $this->getChosenImage();

		/* Try to find another image if we can't find the configured one.
		 */
		if (!is_array($image)) {
			$image = $this->getDefaultImage();

			if (!is_array($image)) {
				$this->getPage()->setVar('preview_block', '');

				$continue = false;
			}
		}

		if ($continue) {
			if ($config['pixel'] > 600){
				$config['pixel'] = 600;
			}

			if ($config['pixel'] < 20) {
				$config['pixel'] = 20;
			}

			$imageurl = "/cms/myImage.php?id={$image['id']}&width={$config['pixel']}&height={$config['pixel']}";

			$this->getPage()->setVar('PREVIEW_IMAGE_URL', $imageurl);
			$this->getPage()->setVar('PREVIEW_IMAGE_DESC', Scrub::text($image['comment']));

			$url = new Url($config['url']);

			if ($url->isUrl()) {
				$this->getPage()->setVar('CONTENT_URL_START', "<a href=\"" . $url->getBase64EncodedRedirect() . "\" target=\"_blank\">");
				$this->getPage()->setVar('CONTENT_URL_END', '</a>');
			}
			else {
				$this->getPage()->setVar('CONTENT_URL_START', '');
				$this->getPage()->setVar('CONTENT_URL_END', '');
			}

			$this->getPage()->parse('preview_block', 'previewimage');
		}
	}
}
