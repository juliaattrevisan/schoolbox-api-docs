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
    const VALID_FILES = [
        'doc', 'docx',
        'xls', 'xlsx',
        'ppt', 'pptx',
        'pdf'
    ];

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
            }
            catch (MapperException $e) {
                $audio = null;
            }

            return [
                'strategy' => 'audio.twig',
                'data' => [
                    'hash' => $this->getFile()->getHash(),
                    'audio' => $audio,
                ]
            ];
        }
        elseif ($mimetype->isVideo()) {

            /* Load from the ResourceVideoMapper to get extra stuff
             * about the media file. Bundle it into a struct
             */
            try {
                $video = ResourceVideoMapper::getInstance()->findByStorageHash($this->getFile()->getHash());
            }
            catch (MapperException $e) {
                $video = null;
            }

            return [
                'strategy' => 'video.twig',
                'data' => [
                    'hash' => $this->getFile()->getHash(),
                    'video' => $video,
                ]
            ];
        }
        elseif ($this->getFile()->getEditableInline()) {

            /* Load purified html into a random div or suchlike
             */
            return [
                'strategy' => 'html.twig'
            ];
        }
        elseif ($this->isCrocodocable()) {

            /* Figger out a crocodoc url
             * \RangeException Use Download File prompt if crocodoc api key is nonexistent on our end
             * \DomainException Use Download File prompt if crocodoc cannot be authenticated on the crocodoc end
             */
            try {
                $authenticator = new Authenticator();
            }
            catch (\RangeException $e) {
                return [
                    'strategy' => 'download.twig'
                ];
            }

            /* Dock this doc into the crocodoc dock
             */
            try {
                $url = (new Facade($authenticator, $this->getSubmission(), $this->getFile(), $this->getUser()))->start();

                return [
                    'strategy' => 'crocodoc.twig',
                    'data' => [
                        'url' => $url
                    ]
                ];
            }
            catch (\DomainException $e) {
                return [
                    'strategy' => 'download.twig'
                ];
            }
        }
        else {

            /* Download File prompt
             */
            return [
                'strategy' => 'download.twig'
            ];
        }
    }

    /**
     * @return bool
     */
    private function isCrocodocable()
    {
        $ext = $this->getFile()->getStorage()->getExtension();

        return strlen(\Cfg::get('crocodoc_api_token')) && in_array($ext, self::VALID_FILES);
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