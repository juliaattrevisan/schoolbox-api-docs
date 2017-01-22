<?php
/**
 * ValidFiles
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan;


class ValidFiles
{

    /**
     * A key/value array of allowable file types as specified
     * by Plagscan themselves
     *
     * @var array
     */
    private $validDocuments = [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc'  => 'application/msword',
        'pdf'  => 'application/pdf',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'odt'  => 'application/vnd.oasis.opendocument.text',
        'txt'  => 'text/plain',
        'rtf'  => 'text/richtext',
        'html' => 'text/html',
    ];

    /**
     * @return array
     */
    public function getValidDocuments()
    {
        return $this->validDocuments;
    }

    /**
     * @param array $validDocuments
     * @return $this
     */
    private function setValidDocuments($validDocuments)
    {
        $this->validDocuments = $validDocuments;
        return $this;
    }

    /**
     * Determine whether filename $documentName is allowed to be sent
     * to plagscan for plagscannage
     *
     * @param $documentName
     * @return bool
     */
    public function isPlagscannable($documentName)
    {
        $documentExtension = pathinfo($documentName, PATHINFO_EXTENSION);

        if (isset($this->validDocuments[$documentExtension])) {
            return true;
        }

        return false;
    }

    /**
     * Get the mime for a $documentName
     *
     * @param $documentName
     * @return mixed
     */
    public function getMime($documentName)
    {
        $documentExtension = pathinfo($documentName, PATHINFO_EXTENSION);

        return $this->validDocuments[$documentExtension];
    }
}