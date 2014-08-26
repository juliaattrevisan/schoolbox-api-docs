<?php
/**
 * Node
 *
 * Render a list of categories and files which sit inside
 * a particular category.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Browser;


use Schoolbox\Collection;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Resource\Browser;
use Schoolbox\Resource\File;
use Schoolbox\Resource\FileMapper;

/**
 * Class Node
 * Extends the default Browser\Node by fetching files in addition to folders
 *
 * @package SocialStream\Browser
 */
class Node extends Browser\Node
{

    /**
     * Combine the categories and files into a single list
     */
    protected function processNode()
    {
        $output = array(
            'breadcrumbs' => $this->getBreadcrumbs(),
            'records'     => array_merge($this->getCategories(), $this->getFiles()),
        );

        $this->setResult($output);
    }


    /**
     * Get all the files for a particular category
     *
     * @param string $mimeFilter
     * @return array
     */
    protected function getFiles($mimeFilter = null)
    {
        $output = array();

        $fileMapper = new FileMapper();
        $mimetypeMapper = new MimetypeMapper();

        //Double check perms, should not get here but just incase
        if ($this->getGroupHandler()->getPermission($this->getNodeId()) < READ_ACCESS) {
            return $output;
        }

        $options = array();

        if ($mimeFilter) {
            $options['mime'] = $mimeFilter;
        }

        /** @var Collection|File[] $allFiles */
        $allFiles = $fileMapper->findByColumn('category', $this->getNodeId(), $options);

        foreach ($allFiles as $currentFile) {

            /* Use a generic default icon for unknown filetypes that
              * could accumulate in the schoolbox
              */
            try {
                $mimetype = $mimetypeMapper->getByFilename($currentFile->getFilename());
            } catch (\Exception $e) {
                $mimetype = $mimetypeMapper->getUnknownMime();
            }

            /* If the mimetype table is empty, it'll spawn with a dunce object
             * SB-3911
             */
            if (!is_numeric($mimetype->getFileTypeID())) {
                $mimetype = $mimetypeMapper->getUnknownMime();
            }

            $record = array(
                'type'   => 'file',
                'fileId' => $currentFile->getId(),
                'name'   => $currentFile->getDescription(),
                'mime'   => $mimetype->getMime(),
                'icon'   => $mimetype->getIconFile(),
                'size'   => $currentFile->getFilesize(),
            );

            $output[] = $record;
        }

        return $output;
    }

}