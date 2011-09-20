<?php

/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 9/20/11
 * Time: 3:56 PM
 * To change this template use File | Settings | File Templates.
 */
 
require_once '../../include/Bootstrap.php';

class Plagscan_ServiceController {
    private $gateway;

    public function __construct () {
        $plagscanApiKey = Config::get('plagscan_api_key');
        $plagscanUsername = Config::get('plagscan_username');

        $this->gateway = new Plagscan_Gateway($plagscanUsername, $plagscanApiKey);
    }

    public function notifyComplete ($documentId) {
        $documentCollection = Plagscan_DocumentMapper::getInstance()->findByColumn('plagscan_id', $documentId);

        $document = $documentCollection[0];

        $document->status = Plagscan_Document::STATUS_COMPLETE;

        Plagscan_DocumentMapper::getInstance()->save($document);
    }
}

$documentId = $_SERVER["QUERY_STRING"];

if ( !empty($documentId) ) {
    $serviceController = new Plagscan_ServiceController;
    
    echo $serviceController->notifyComplete($documentId);
}