<?php
/**
 * Component_Eportfolio_TextboxMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Component\Eportfolio;
use Schoolbox;

class TextboxMapper extends Schoolbox\Mapper
{
	/**
	 * Define column/property mapping
	 *
	 * @return void
	 */
	protected function mapping()
    {
		$this->hasTable('eportfolio_component_textbox_item');
		$this->hasModel(Textbox::class);

		$this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('component_instance_id', self::TYPE_INTEGER);
        $this->uses('content', self::TYPE_STRING);
        $this->uses('sequence', self::TYPE_INTEGER);
	}
}