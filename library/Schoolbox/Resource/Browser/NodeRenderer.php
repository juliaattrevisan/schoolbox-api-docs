<?php
/**
 * NodeRenderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser;


use SitePage;

class NodeRenderer
{

    /**
     * The usual sitepage
     *
     * @var SitePage
     */
    protected $page = null;

    /**
     * The contents of the current node
     *
     * @var array
     */
    protected $nodeData = array();

    /** @var string */
    protected $handle;


    /**
     * Constructor
     *
     * @param SitePage $page
     * @param array    $nodeData
     * @param string   $handle
     */
    public function __construct($page, $nodeData, $handle = TEMPLATE_content)
    {
        $this->setPage($page);
        $this->setNodeData($nodeData);
        $this->setHandle($handle);
    }


    /**
     * Render tree and excrete it
     */
    public function render($foldersSelectable = true)
    {
        // Setup the various blocks that need setting
        $this->setupBlocks();

        /* Current node is breadcrumbs[0]
         */
        $this->renderCurrentNode();

        /* Render all the breadcrumbs
         */
        $this->renderBreadcrumbs();

        /* Render all the records
         */
        $this->renderContents($foldersSelectable);

        /* Send it out
         */
        $this->getPage()->setAjax();
        $this->getPage()->template->pparse('finaloutput', $this->getHandle());
    }


    /**
     * Set the various blocks that need setting
     */
    protected function setupBlocks()
    {
        $this->getPage()->setFile($this->getHandle(), 'component/homepage/socialstream/node.html');
        $this->getPage()->setBlock($this->getHandle(), 'breadcrumb', 'breadcrumb_block');
        $this->getPage()->setBlock($this->getHandle(), 'node_category', 'node_category_block');

        $blocks = array('breadcrumb', 'node_category', 'node_file', 'use_folder');

        foreach ($blocks as $b) {
            $bk = $b . '_block';
            $this->getPage()->setBlock($this->getHandle(), $b, $bk);
            $this->getPage()->setVar($bk, '');
        }

    }


    /**
     * Render the title of the node you're currently in
     */
    protected function renderCurrentNode()
    {
        $nodeData = $this->getNodeData();

        $currentNode = end(array_values($nodeData['breadcrumbs']));

        // Fudge values for home node
        $output = array(
            'NODE_NAME' => 'Home',
            'NODE_ID'   => 0,
            'NODE_LINK' => '/',
        );
        if ($currentNode) {
            $output = array(
                'NODE_NAME' => htmlspecialchars($currentNode['name']),
                'NODE_ID'   => $currentNode['categoryId'],
                'NODE_LINK' => '/homepage/' . $currentNode['categoryId'],
            );
        }
        $this->getPage()->setVar($output);
    }


    /**
     * Render breadcrumbs for this node
     */
    protected function renderBreadcrumbs()
    {
        $nodeData = $this->getNodeData();

        $allBreadcrumbs = $nodeData['breadcrumbs'];

        $j = 0;
        foreach ($allBreadcrumbs as $currentBreadcrumb) {
            $this->getPage()->setVar(array(
                'BREADCRUMB_CLASS'       => (count($allBreadcrumbs) - 1 == $j) ? 'last' : '',
                'BREADCRUMB_CATEGORY_ID' => $currentBreadcrumb['categoryId'],
                'BREADCRUMB_NAME'        => $currentBreadcrumb['name']
            ));

            $this->getPage()->parse('breadcrumb_block', 'breadcrumb', true);
            $j++;
        }
    }


    /**
     * Render all the contents of the current node
     */
    protected function renderContents($foldersSelectable = true)
    {
        $nodeData = $this->getNodeData();

        $allRecords = $nodeData['records'];

        for ($i = 0; $i < count($allRecords); $i++) {
            $currentRecord = $allRecords[$i];

            switch ($currentRecord['type']) {
                case 'category':
                    $this->renderFolder($currentRecord, $i);
                    break;
                case 'file':
                    $this->renderFile($currentRecord, $i);
                    break;
                default:
                    break;
            }
        }

        // only render the 'use this folder' block if it's been asked for
        if ($foldersSelectable) {
            $this->getPage()->parse('use_folder_block', 'use_folder');
        }
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


    /**
     * Render a category record
     *
     * @param $currentRecord
     * @param $i
     */
    protected function renderFolder($currentRecord, $i)
    {
        $this->getPage()->setVar(array(
            'NODE_CATEGORY_ON_OFF' => $i % 2 ? '' : 'dark',
            'NODE_CATEGORY_ID'     => $currentRecord['categoryId'],
            'NODE_CATEGORY_NAME'   => $currentRecord['name'],
            'NODE_CATEGORY_TYPE'   => $currentRecord['folderType'],
        ));

        $this->getPage()->parse('node_category_block', 'node_category', true);
    }


    /**
     * Set page
     *
     * @param SitePage $page
     */
    protected function setPage($page)
    {
        $this->page = $page;
    }


    /**
     * Get page
     *
     * @return SitePage
     */
    protected function getPage()
    {
        return $this->page;
    }


    /**
     * Set node data
     *
     * @param array $nodeData
     */
    protected function setNodeData($nodeData)
    {
        $this->nodeData = $nodeData;
    }


    /**
     * Get node data
     *
     * @return array
     */
    protected function getNodeData()
    {
        return $this->nodeData;
    }


    /**
     * @param string $handle
     * @return self
     */
    protected function setHandle($handle)
    {
        $this->handle = $handle;
        return $this;
    }


    /**
     * @return string
     */
    protected function getHandle()
    {
        return $this->handle;
    }

}
