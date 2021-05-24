<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;

/**
 * Class ValidFiles
 *
 * Filetypes accepted by plag
 *
 * @package Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan
 */
class ValidFiles
{

    /**
     * A key/value array of allowable file types as specified
     * by Plagscan themselves
     *
     * @var array
     */
    public static $validDocuments = [
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
        'keynote' => 'application/vnd.apple.keynote',
        'pages'   => 'application/vnd.apple.pages',
        'numbers' => 'application/vnd.apple.numbers',
    ];


    /**
     * Determine whether filename $documentName is allowed to be sent
     * to plagscan for plagscannage
     *
     * @param string $documentName
     * @return bool
     */
    public function isPlagscannable(string $documentName): bool
    {
        $documentExtension = strtolower(pathinfo($documentName, PATHINFO_EXTENSION));

        if (isset(self::$validDocuments[$documentExtension])) {
            return true;
        }

        return false;
    }

    /**
     * Get the mime for a $documentName
     *
     * @param string $documentName
     * @return string|null
     */
    public function getMime(string $documentName)
    {
        $documentExtension = pathinfo($documentName, PATHINFO_EXTENSION);

        return self::$validDocuments[$documentExtension];
    }
}
