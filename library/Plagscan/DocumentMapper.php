<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
class Plagscan_DocumentMapper extends Mapper {
    private $gateway;

    protected function __construct () {
        parent::__construct();

        $plagscanApiKey = Config::get('plagscan_api_key');
        $plagscanUsername = Config::get('plagscan_username');

        $this->gateway = new Plagscan_Gateway($plagscanUsername, $plagscanApiKey);
    }

    protected function mapping () {
        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => Mapper::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'file_id',
            'property'   => 'fileId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'plagscan_id',
            'property'   => 'plagscanId',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'status',
            'property'   => 'status',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'score',
            'property'   => 'score',
            'type'       => Mapper::TYPE_STRING,
        ));

        parent::mapping();
    }

    public function save ($dirtyDocument) {
        if ( !$this->findById($dirtyDocument->id) ) {
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