<?php

/**
 * ResponseFile
 *
 * Figure out what fragment to load into Inline Grader
 * based on the file type.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\InlineGrader\Factory;

use \Schoolbox\Learning\Assessment\DueWork\Response\Model as Submission;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use \Schoolbox\User;
use \Schoolbox\Video\ResourceVideoMapper;
use \Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Learning\InlineGrader\Kami;

class ResponseFile
{
    /**
     * Supported mime types
     *
     * @var array
     */
    private static $allowedFileTypes = array(
        'doc', 'docx',
        'xls', 'xlsx',
        'ppt', 'pptx',
        'pdf'
    );

    /**
     * @var Kami\Mapper
     */
    private $kamiMapper;

    /**
     * @var Kami\Connector
     */
    private $kamiConnector;


    /**
     * ResponseFile constructor.
     *
     * @param Kami\Mapper    $kamiMapper
     * @param Kami\Connector $kamiConnector
     */
    public function __construct(Kami\Mapper $kamiMapper, Kami\Connector $kamiConnector)
    {
        $this->kamiMapper    = $kamiMapper;
        $this->kamiConnector = $kamiConnector;
    }


    /**
     * Figure out which fragment to load
     *
     * @param SubmissionFile $file
     * @param User           $user
     * @return array
     * @throws Kami\Exception\ApiException If cannot fetch a document for reasons other than simply not existing.
     * @throws Kami\Exception\NotFound
     * @throws Kami\Exception\ResponseDecodeException If unable to decode a server response
     * @throws \InvalidArgumentException If cannot convert Kami request or response to a string
     * @throws \LogicException If attempting to update the status of a kami doc that is not processing.
     * @throws \PDOException If something went wrong when saving the kami document's status
     * @throws \UnexpectedValueException If unexpected status change is reported from Kami API
     * @throws Kami\Exception\EmptySiteSchoolCode
     */
    public function produce(SubmissionFile $file, User $user): array
    {
        $kamiViewSession = $this->getKamiViewSession($file, $user);

        if ($kamiViewSession) {
            return [
                'strategy' => 'kami.twig',
                'data'     => [
                    'session' => $kamiViewSession,
                ],
            ];
        }

        /* TODO: Check that file exists and that you can access it
         * If it doesn't exist, 404 File Not Found
         * If you're not allowed, 403 Forbidden
         */
        $mimetype = $file->getStorage()->getMimetype();

        if ($mimetype->isImage()) {

            /* Load an image html tag
             */
            return [
                'strategy' => 'image.twig',
            ];
        }

        if ($mimetype->isAudio()) {
            /* Load from the ResourceVideoMapper to get extra stuff
             * about the media file. Bundle it into a struct
             */
            try {
                $audio = ResourceVideoMapper::getInstance()->findByStorageHash($file->getHash());
            }
            catch (MapperException $e) {
                $audio = null;
            }

            return [
                'strategy' => 'audio.twig',
                'data' => [
                    'hash' => $file->getHash(),
                    'audio' => $audio,
                ]
            ];
        }

        if ($mimetype->isVideo()) {
            /* Load from the ResourceVideoMapper to get extra stuff
             * about the media file. Bundle it into a struct
             */
            try {
                $video = ResourceVideoMapper::getInstance()->findByStorageHash($file->getHash());
            }
            catch (MapperException $e) {
                $video = null;
            }

            return [
                'strategy' => 'video.twig',
                'data' => [
                    'hash' => $file->getHash(),
                    'video' => $video,
                ]
            ];
        }

        if ($file->getEditableInline()) {
            /* Load purified html into a random div or suchlike
             */
            return [
                'strategy' => 'html.twig'
            ];
        }

        /* Download File prompt
         */
        return [
            'strategy' => 'download.twig'
        ];
    }


    /**
     * Fetch a Kami View Session if one is available
     *
     * @param SubmissionFile $file
     * @param User           $user
     * @return null|Kami\Session
     * @throws Kami\Exception\ApiException If cannot fetch a document for reasons other than simply not existing.
     * @throws Kami\Exception\NotFound
     * @throws Kami\Exception\ResponseDecodeException If unable to decode a server response
     * @throws \InvalidArgumentException If cannot convert Kami request or response to a string
     * @throws \LogicException If attempting to update the status of a kami doc that is not processing.
     * @throws \PDOException If something went wrong when saving the kami document's status
     * @throws \UnexpectedValueException If unexpected status change is reported from Kami API
     * @throws Kami\Exception\EmptySiteSchoolCode
     */
    private function getKamiViewSession(SubmissionFile $file, User $user): ?Kami\Session
    {
        // Short-circuit if not enabled
        if (!$this->kamiConnector->isEnabled()) {
            return null;
        }

        // Has this file been uploaded to kami?
        $kamiModel = $this->kamiMapper->findForSubmissionFile($file);
        if (!$kamiModel || !$kamiModel->isUploaded()) {
            return null;
        }

        // Was it still being processed by Kami last time we checked?
        if ($kamiModel->isStatusProcessing()) {
            try {
                $checker   = new Kami\StatusCheck($this->kamiMapper, $this->kamiConnector);
                $kamiModel = $checker->updateStatus($kamiModel);
            } catch (Kami\Exception\EmptyApiKeyException $e) {
                // Should be impossible here due to short-circuit at start of method.
                return null;
            }
        }

        // Still not finished processing?
        if (!$kamiModel->isStatusDone()) {
            return null;
        }

        try {
            // Finished processing. Get a fresh view session
            return $this->kamiConnector->viewSession($kamiModel->getKamiDocumentId(), $user, true);
        } catch (Kami\Exception\EmptyApiKeyException $e) {
            // Should be impossible here due to short-circuit at start of method.
            return null;
        }
    }

    /* Getters/Setters
     */

    /**
     * @return array
     */
    public static function getAllowedFileTypes()
    {
        return self::$allowedFileTypes;
    }
}
