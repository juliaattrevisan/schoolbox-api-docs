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

	public function save ($dirtyDocument) {
		if ( !$this->findById($dirtyDocument->id)->id ) {
			$documentId = $this->gateway->submit($dirtyDocument->file['name'], $dirtyDocument->file['file']);

			if ( !empty($documentId) ) {
				$this->gateway->check($documentId);

				$dirtyDocument->plagscanId = $documentId;
				$dirtyDocument->status = Plagscan_Document::STATUS_QUEUED;
			}
			else {
				return false;
			}
		}

		return parent::save($dirtyDocument);
	}
}