<?php

/**
 * Upload
 *
 * Asynchronous daemon uploader for crocodoc
 *
 * This allows crocodoc-friendly material to be transferred asynchronously
 * using a daemon, rather than forcing people to upload files to crocodoc
 * inline in the browser, thus enduring a perhaps-terrible wait
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\InlineGrader\Asynchronous;

use \Psr\Log\LoggerInterface;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as DueWorkSubmissionFile;
use Schoolbox\Learning\InlineGrader\Authenticator;
use Schoolbox\Learning\InlineGrader\Document;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SingleFile;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper as SingleFileMapper;

class Upload
{

    /**
     * @var Authenticator
     */
    private $authenticator;

    /**
     * A logger
     * 
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var SingleFileMapper
     */
    private $singleFileMapper;

    /**
     * Upload constructor.
     * @param Authenticator $authenticator
     * @param LoggerInterface $logger
     * @param SingleFileMapper $singleFileMapper
     */
    public function __construct(Authenticator $authenticator, LoggerInterface $logger, SingleFileMapper $singleFileMapper)
    {
        $this->setAuthenticator($authenticator);
        $this->setLogger($logger);
        $this->setSingleFileMapper($singleFileMapper);
    }

    /**
     * Find files that can be put into crocodoc and transmit them asynchronously
     */
    public function uploadPendingCrocodocs()
    {
        $allFiles = $this->getSingleFileMapper()->findPendingCrocodocUpload();

        /** @var SingleFile $currentFile */
        foreach ($allFiles as $currentFile) {
            $prefix = "{$currentFile->getId()} {$currentFile->getBasename()}";

            $this->getLogger()->notice("$prefix: starting upload");

            /* Swamptastically convert it into a format that Document accepts
             */
            $responseFile = (new DueWorkSubmissionFile())
                ->setId($currentFile->getId())
                ->setStorage($currentFile->getStorage())
                ->setEditableInline($currentFile->getEditableInline())
                ->setCrocodocId($currentFile->getUuid())
                ->setFilename($currentFile->getFilename())
                ->setPlagscan($currentFile->getPlagscan());

            try {
                $document = new Document($responseFile);
                $result = $document->upload();

                if ($result['errors']) {
                    $this->getLogger()->error($result['errors']);

                    switch ($result['responseCode']) {
                        case 401:
                            // This is an authentication error, caused by a wrong token.
                            // Give up because the token is not going to fix itself.
                            $this->getLogger()->error("{$prefix}: Authentication error received - exiting");
                            exit;
                        default:
                            break;
                    }
                }
                else {
                    $this->getLogger()->notice("{$prefix}: completed successfully");
                }
            } catch (\RangeException $e) {
                $this->getLogger()->error($e->getMessage());
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * @return Authenticator
     */
    private function getAuthenticator()
    {
        return $this->authenticator;
    }

    /**
     * @param Authenticator $authenticator
     * @return $this
     */
    private function setAuthenticator($authenticator)
    {
        $this->authenticator = $authenticator;
        return $this;
    }

    /**
     * @return LoggerInterface
     */
    private function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     * @return $this
     */
    private function setLogger($logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @return SingleFileMapper
     */
    private function getSingleFileMapper()
    {
        return $this->singleFileMapper;
    }

    /**
     * @param SingleFileMapper $singleFileMapper
     * @return $this
     */
    private function setSingleFileMapper($singleFileMapper)
    {
        $this->singleFileMapper = $singleFileMapper;
        return $this;
    }
}