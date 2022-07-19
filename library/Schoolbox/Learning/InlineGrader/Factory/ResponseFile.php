<?php

/**
 * ResponseFile
 *
 * Figure out what fragment to load into Inline Grader
 * based on the file type.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\InlineGrader\Factory;

use League\Flysystem\FilesystemException;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\User;
use Schoolbox\Video\ResourceVideoMapper;
use Schoolbox\Mapper\Exception as MapperException;
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
     * ResponseFile constructor.
     *
     * @param Kami\Mapper     $kamiMapper
     * @param Kami\Connector  $kamiConnector
     * @param TermMapper      $termMapper
     * @param FilesystemProxy $filesystemProxy,
     */
    public function __construct(
        private Kami\Mapper $kamiMapper,
        private Kami\Connector $kamiConnector,
        private TermMapper $termMapper,
        private FilesystemProxy $filesystemProxy,
    ) {
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
            } catch (MapperException $e) {
                $audio = null;
            }

            return [
                'strategy' => 'audio.twig',
                'data' => [
                    'hash'  => $file->getHash(),
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
            } catch (MapperException $e) {
                $video = null;
            }

            return [
                'strategy' => 'video.twig',
                'data' => [
                    'hash'  => $file->getHash(),
                    'video' => $video,
                ]
            ];
        }

        if ($file->getEditableInline()) {
            /* Load purified html into a random div or suchlike
             */
            $content = null;
            try {
                $content = $this->filesystemProxy->read($file);
            } catch (FilesystemException) {
                // noop: fallback to null
            }
            return [
                'strategy' => 'html.twig',
                'data'     => [
                    'hash'    => $file->getHash(),
                    'content' => $content,
                ],
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
                $checker   = new Kami\StatusChecker($this->kamiMapper, $this->kamiConnector);
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

            /* If we're in an archived period, make this read only
             */
            $term = $this->termMapper->findForDate($file->getSubmission()->getAssessable()->getDueAt());

            if ($term->getArchived()) {
                return $this->kamiConnector->viewSession($kamiModel->getKamiDocumentId(), $user, false);
            }

            /* Otherwise present it with edit options
             */
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
