<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\File\Remote;

use Alaress;
use Schoolbox\File\ExtensionMapper;
use Schoolbox\File\MimetypeMapper;

class GDriveFetcher extends Alaress\File\Remote\GDriveFetcher
{

    /**
     * Derive the file extension from the metadata
     *
     * @param array $metadata
     * @return string
     */
    protected function deriveFileExtension(array $metadata)
    {
        $ext = parent::deriveFileExtension($metadata);

        if ($ext) {
            // return if we got a match
            return $ext;
        }

        try {
            // oh, no dice? Now we attempt to use the mimetype to reverse engineer an extension
            $mime = MimetypeMapper::getInstance()->findOneByColumn('mime', $metadata['mimeType']);
            $exts = ExtensionMapper::getInstance()->findByColumn('fileTypeID', $mime->getId());

            if ($exts->count()) {
                // if there's at least one extension, return the first one
                return '.' . $exts->first()->getExtension();
            }

            // give the exception something to go on
            $e = null;

        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop, exception thrown outside the catch
        }

        throw new \OutOfBoundsException("Given file type '{$metadata['mimeType']}' is not an allowed file", 0, $e);
    }

}
