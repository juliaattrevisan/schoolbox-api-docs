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

        if ( $documentCollection ) {
            $document = $documentCollection[0];

            $documentSummary = $this->gateway->retrieve($documentId, 0);

            $document->score = $documentSummary->LEVEL / 10;
            $document->status = Plagscan_Document::STATUS_COMPLETE;

            Plagscan_DocumentMapper::getInstance()->save($document);
        }
        else {
            return false;
        }
    }
}

/* Handles responses from PlagScan which indicate a document has completed plagiarism scanning */
$documentId = $_SERVER["QUERY_STRING"];

if ( !empty($documentId) ) {
    $serviceController = new Plagscan_ServiceController;
    
    echo $serviceController->notifyComplete($documentId);
}