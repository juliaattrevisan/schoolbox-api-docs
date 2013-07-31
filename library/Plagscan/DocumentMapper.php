<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

class Plagscan_DocumentMapper extends Schoolbox\Mapper {

	/**
	 * @var Plagscan_Gateway
	 */
	private $gateway;

	protected function setup() {
		parent::setup();
		$plagscanApiKey = Config::get('plagscan_api_key');
		$plagscanUsername = Config::get('plagscan_username');
		$this->gateway = new Plagscan_Gateway($plagscanUsername, $plagscanApiKey);
	}

	protected function mapping () {
		$this->hasTable('plagscan_document');
		$this->hasModel('Plagscan_Document');
		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'file_id',
			'property'   => 'fileId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'plagscan_id',
			'property'   => 'plagscanId',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'status',
			'property'   => 'status',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasMap(array(
			'column'     => 'score',
			'property'   => 'score',
			'type'       => self::TYPE_STRING,
		));

		$this->hasMap(array(
			'column'     => 'html',
			'property'   => 'html',
			'type'       => self::TYPE_STRING,
		));
	}

	/**
	 * Saves a Plagscan document.
	 *
	 * @param Plagscan_Document $dirtyDocument
	 *
	 * @return array
	 */
	public function save ($dirtyDocument) {
		//
		// Document not found already? Submit to the gateway
		try {
			//
			// Don't do anything if $existing_document isn't found
			$existing_document = $this->findById($dirtyDocument->getId());
			$file = $dirtyDocument->getFile();
			$documentId = $this->gateway->submit($file['name'], $file['file']);

			if ( !empty($documentId) ) {
				$this->gateway->check($documentId);

				$dirtyDocument->setPlagscanId($documentId);
				$dirtyDocument->setStatus(Plagscan_Document::STATUS_QUEUED);
			}
			else {
				return false;
			}
		}
		catch (Schoolbox\Mapper\Exception $e) {}

		return parent::save($dirtyDocument);
	}
}