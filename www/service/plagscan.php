<?php

/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 9/20/11
 * Time: 3:56 PM
 * To change this template use File | Settings | File Templates.
 */

require_once '../../library/Bootstrap.php';

use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Mapper as PlagscanMapper;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Gateway as PlagscanGateway;

/* TODO wtf
 */
class Plagscan_ServiceController {
    private $gateway;

    public function __construct () {
        $plagscanApiKey = Config::get('plagscan_api_key');
        $plagscanUsername = Config::get('plagscan_username');

        $this->gateway = new PlagscanGateway($plagscanUsername, $plagscanApiKey);
    }

    /**
     * Executed when callback from PlagScan is triggered, and requests the report from PlagScan
     * @param $documentId
     * @return bool
     */
    public function notifyComplete ($documentId) {
        try {
            /* @var $document Plagscan */
            $document = PlagscanMapper::getInstance()->findOneByColumn('plagscan_id', $documentId);
            if (!($document instanceof Plagscan)) {
                return false;
            }

            $plagscanStat = $this->gateway->retrieve($documentId, PlagscanGateway::MODE_RETRIEVE_STATISTICS_ONLY);
            $plagscanHtml = $this->gateway->retrieve($documentId, PlagscanGateway::MODE_RETRIEVE_HTML_INTERACTIVE);

            $document->setScore($plagscanStat->LEVEL / 10);
            $document->setStatus(Plagscan::STATUS_COMPLETE);

            $document->setHtml($plagscanHtml);

            PlagscanMapper::getInstance()->save($document);
        }
        catch (Schoolbox\Mapper\Exception $e) {
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
