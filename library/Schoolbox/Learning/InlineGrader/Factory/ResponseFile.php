<?php

/**
 * ResponseFile
 *
 * Figure out what fragment to load into Inline Grader
 * based on the file type, its crocodocableness, etc.
 *
 * A reimplementation of logic contained within the old
 * Crocodoc\Renderer\Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\InlineGrader\Factory;

use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as Submission;
use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
use \Schoolbox\Learning\InlineGrader\Authenticator;
use \Schoolbox\Learning\InlineGrader\Facade;
use \Schoolbox\User;
use \Schoolbox\Video\ResourceVideoMapper;
use \Schoolbox\Mapper\Exception as MapperException;

class ResponseFile
{
    /**
     * Supported mime types
     *
     * @var array
     */
    private $allowedFileTypes = array(
        'doc', 'docx',
        'xls', 'xlsx',
        'ppt', 'pptx',
        'pdf'
    );

    /**
     * @var Submission
     */
    private $submission;

    /**
     * @var SubmissionFile
     */
    private $file;

    /**
     * @var User
     */
    private $user;

    /**
     * ResponseFile constructor.
     *
     * @param Submission $submission
     * @param SubmissionFile $file
     * @param User $user
     */
    public function __construct(Submission $submission, SubmissionFile $file, User $user)
    {
        $this->setSubmission($submission);
        $this->setFile($file);
        $this->setUser($user);
    }

    /**
     * Figure out which fragment to load
     */
    public function produce()
    {
        /* TODO: Check that file exists and that you can access it
         * If it doesn't exist, 404 File Not Found
         * If you're not allowed, 403 Forbidden
         */

        $mimetype = $this->getFile()->getStorage()->getMimetype();

        if ($mimetype->isImage()) {

            /* Load an image html tag
             */
            return [
                'strategy' => 'image.twig',
            ];
        }
        elseif ($mimetype->isAudio()) {

            /* Load from the ResourceVideoMapper to get extra stuff
             * about the media file. Bundle it into a struct
             */
            try {
                $audio = ResourceVideoMapper::getInstance()->findByStorageHash($this->getFile()->getHash());

                return [
                    'strategy' => 'audio.twig',
                    'data' => [
                        'audio' => $audio,
                    ]
                ];
            }
            catch (MapperException $e) {
            }

            return [];
        }
        elseif ($mimetype->isVideo()) {

            /* Load from the ResourceVideoMapper to get extra stuff
             * about the media file. Bundle it into a struct
             */
            try {
                $video = ResourceVideoMapper::getInstance()->findByStorageHash($this->getFile()->getHash());

                return [
                    'strategy' => 'video.twig',
                    'data' => [
                        'video' => $video,
                    ]
                ];
            }
            catch (MapperException $e) {
            }

            return [];
        }
        elseif ($this->getFile()->isOnlineSubmission()) {

            /* Load purified html into a random div or suchlike
             */
            return [
                'strategy' => 'html.twig'
            ];
        }
        elseif ($this->isCrocodocable()) {

            /* Figger out a crocodoc url
             */
            $url = (new Facade($this->getFile(), $this->getSubmission(), $this->getUser()))->start();

            /* Dock this doc into the crocodoc dock
             */
            return [
                'data' => [
                    'url' => $url
                ],
                'strategy' => 'crocodoc.twig'
            ];
        }
        else {

            /* Download File prompt
             */
            return [
                'strategy' => 'none.twig'
            ];
        }
    }

    /**
     * @return bool
     */
    private function isCrocodocable()
    {
        $ext = $this->getFile()->getStorage()->getExtension();

        return strlen(\Cfg::get('crocodoc_api_token')) && in_array($ext, $this->getAllowedFileTypes());
    }

    /* Getters/Setters
     */

    /**
     * @return Submission
     */
    protected function getSubmission()
    {
        return $this->submission;
    }

    /**
     * @param Submission $submission
     * @return $this
     */
    protected function setSubmission($submission)
    {
        $this->submission = $submission;
        return $this;
    }

    /**
     * @return SubmissionFile
     */
    protected function getFile()
    {
        return $this->file;
    }

    /**
     * @param SubmissionFile $file
     * @return $this
     */
    protected function setFile($file)
    {
        $this->file = $file;
        return $this;
    }

    /**
     * @return array
     */
    protected function getAllowedFileTypes()
    {
        return $this->allowedFileTypes;
    }

    /**
     * @param array $allowedFileTypes
     * @return $this
     */
    protected function setAllowedFileTypes($allowedFileTypes)
    {
        $this->allowedFileTypes = $allowedFileTypes;
        return $this;
    }

    /**
     * @return User
     */
    protected function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return $this
     */
    protected function setUser($user)
    {
        $this->user = $user;
        return $this;
    }
}