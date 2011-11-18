<?php
/**
 * Eportfolio_Image_Gallery
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Eportfolio_Image_Gallery extends Eportfolio_Image_Abstract {
	const IMAGES_PER_ROW = 4;

	/**
	 * Setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->getPage()->setBlock($this->getComponentHandle(), 'thumb_row', 'row_block');
		$this->getPage()->setBlock('thumb_row', 'thumb_cell', 'cell_block');
	}

	/**
	 * Set up default config stuff
	 *
	 * @return void
	 */
	private function setDefaultConfig() {
		$config = $this->getConfig();

		if (is_numeric($config['size'])) {
			$limit = $config['size'] * self::IMAGES_PER_ROW;
		}
	}

	/**
	 * Render images
	 *
	 * @return void
	 */
	public function render() {
		$this->setup();

		$eportfolioHandler = new Eportfolio_Container($this->getUserId());

		$imageList = $eportfolioHandler->getImages($this->getUserId());

		$count = 1;

		if (is_array($imageList) ) {
			foreach ($imageList as $thumbnail) {
				$this->getPage()->setVar('THUMBNAIL', "
					<a style='height:100px' class=\"float-left thumb\" href=\"/cms/image.php?myFile=1&id={$thumbnail['id']}&height=600&width=800\" rel=\"lightbox[portal]\" title=\"{$thumbnail['description']}\">
					<img src=\"/cms/image.php?myFile=1&id={$thumbnail['id']}&height=".Cfg::get('thumbnail_size')."&width=".Cfg::get('thumbnail_size')."&resample=0\" alt=\"{$thumbnail['description']}\" border=\"0\">
					</a>");

				$this->getPage()->parse('cell_block', 'thumb_cell', true);

				if (($count % self::IMAGES_PER_ROW) == 0) {
					$this->getPage()->parse('row_block', 'thumb_row', true);
					$this->getPage()->setVar('cell_block', '');
				}

				$count++;
			}

			for (;$count % self::IMAGES_PER_ROW != 0; $count++) {
				$this->getPage()->setVar('THUMBNAIL', '');
				$this->getPage()->parse('cell_block', 'thumb_cell', true);
			}

			$this->getPage()->parse('row_block', 'thumb_row', true);
		}
		else {
			$this->getPage()->setVar('row_block', '');
		}
	}
}
