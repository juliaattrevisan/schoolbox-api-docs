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

    /**
     * Executed when callback from PlagScan is triggered, and requests the report from PlagScan
     * @param $documentId
     * @param int $mode
     * @return bool
     */
    public function notifyComplete ($documentId) {
        $documentCollection = Plagscan_DocumentMapper::getInstance()->findByColumn('plagscan_id', $documentId);

        if ( $documentCollection ) {
            /* @var $document Plagscan_Document */
            $document = $documentCollection->first();

            $plagscanStat = $this->gateway->retrieve($documentId, Plagscan_Gateway::MODE_RETRIEVE_STATISTICS_ONLY);
            $plagscanHtml = $this->gateway->retrieve($documentId, Plagscan_Gateway::MODE_RETRIEVE_HTML_INTERACTIVE);

            $document->setScore($plagscanStat->LEVEL / 10);
            $document->setStatus(Plagscan_Document::STATUS_COMPLETE);

            $document->setHtml($plagscanHtml);

            Plagscan_DocumentMapper::getInstance()->save($document);
        }
        else {
            return false;
        }
        return true;
    }

}

/* Handles responses from PlagScan which indicate a document has completed plagiarism scanning */
$documentId = $_SERVER["QUERY_STRING"];

if ( !empty($documentId) ) {
    $serviceController = new Plagscan_ServiceController;
    
    echo $serviceController->notifyComplete($documentId);
}