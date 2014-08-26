<?php
/**
 * NodeRenderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Browser;


use Schoolbox\Resource\Browser;

class NodeRenderer extends Browser\NodeRenderer
{

    /**
     * Set the various blocks that need setting
     */
    protected function setupBlocks()
    {
        parent::setupBlocks();
        $this->getPage()->setBlock($this->getHandle(), 'node_file', 'node_file_block');
        $this->getPage()->setVar('node_file_block', '');
    }


    /**
     * Render all the contents of the current node
     */
    protected function renderContents()
    {
        $nodeData = $this->getNodeData();

        $allRecords = $nodeData['records'];

        for ($i = 0; $i < count($allRecords); $i++) {
            $currentRecord = $allRecords[$i];

            switch ($currentRecord['type']) {
                case 'category':
                    $this->renderCategory($currentRecord, $i);
                    break;
                case 'file':
                    $this->renderFile($currentRecord, $i);
                    break;
                default:
                    break;
            }
        }

        $this->getPage()->parse('use_folder_block', 'use_folder');
    }


    /**
     * Render a file record
     *
     * @param $currentRecord
     * @param $i
     */
    protected function renderFile($currentRecord, $i)
    {
        $this->getPage()->setVar(array(
            'NODE_FILE_ON_OFF' => $i % 2 ? '' : 'dark',
            'NODE_FILE_ID'     => $currentRecord['fileId'],
            'NODE_FILE_NAME'   => $currentRecord['name'],
            'NODE_FILE_ICON'   => $currentRecord['icon'],
            'NODE_MIME_TYPE'   => $currentRecord['mime'],
            'NODE_FILE_SIZE'   => $currentRecord['size']
        ));

        $this->getPage()->parse('node_file_block', 'node_file', true);
    }

}
