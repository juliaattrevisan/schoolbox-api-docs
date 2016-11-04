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

use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
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
     * @var SubmissionFile
     */
    private $file;

    /**
     * ResponseFile constructor.
     *
     * @param SubmissionFile $file
     */
    public function __construct(SubmissionFile $file)
    {
        $this->setFile($file);
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
            // iframe crocodoc url
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
}