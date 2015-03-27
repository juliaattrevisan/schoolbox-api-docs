<?php
/**
 * IFrame
 *
 * Load url in an iframe
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;

use Schoolbox\Resource\Storage\Mapper as StorageMapper;

class IFrame extends Base {

    /**
     * Content Types for this strategy
     *
     * @var array
     */
    private $contentTypes = [
        'html', 'htm', 'txt', 'csv', 'pdf', 'swf',
    ];

    /**
     * IFrameable content
     *
     * @return string
     */
    public function test() {
        if ($this->isStorage()) {
            $storageMapper = new StorageMapper();

            try {
                $item = $storageMapper->findByHash($this->getHash());

                if (in_array($item->getExtension(), $this->getContentTypes())) {
                    return [
                        'type' => 'html-fragment',
                        'twig' => [
                            'template' => '@preview/Strategy/IFrame.twig',
                            'data' => [
                                'src' => $this->getUrl()
                            ]
                        ]
                    ];
                }
            }
            catch (\Exception $e) {
                error(41);
            }
        }

        return $this->testNext();
    }

    /* Getters/Setters
     */

    /**
     * Set content types
     *
     * @param array $contentTypes
     */
    private function setContentTypes($contentTypes)
    {
        $this->contentTypes = $contentTypes;
    }

    /**
     * Get content types
     *
     * @return array
     */
    private function getContentTypes()
    {
        return $this->contentTypes;
    }
}