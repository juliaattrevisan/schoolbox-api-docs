<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser;


use OutOfBoundsException;
use Schoolbox\Resource\Browser;

class NodeFactory
{

    const STORE  = 'store';
    const SBFS   = 'browse';
    const MYFILE = 'myfile';
    const WEBDAV = 'webdav';
    const OFFICE = 'office365';

    /** @var callable[] */
    protected $loaders;


    /**
     * Create lazy loaders
     */
    public function __construct()
    {
        $this->add(self::SBFS, function ($nodeId, $mimeFilter = null) {
            return new Browser\Resources\Node($nodeId, $mimeFilter);
        });

        $this->add(self::MYFILE, function ($nodeId, $mimeFilter = null) {
            return new Browser\MyFiles\Node($nodeId, $mimeFilter);
        });

        $this->add(self::WEBDAV, function ($nodeId, $mimeFilter = null) {
            return new Browser\WebDav\Node($nodeId, $mimeFilter);
        });

        $this->add(self::OFFICE, function ($nodeId, $mimeFilter = null, $oAuthToken = null) {
            return new Browser\Office\Node($nodeId, $mimeFilter, $oAuthToken);
        });

    }


    /**
     * @param string   $label
     * @param callable $lazyload
     */
    protected function add($label, $lazyload)
    {
        $this->loaders[$label] = $lazyload;
    }


    /**
     * @param string $label
     * @param int    $nodeId
     * @param string $mimeFilter
     * @param string $oAuthToken
     * @return NodeInterface
     * @throws OutOfBoundsException
     */
    public function build($label, $nodeId, $mimeFilter = null, $oAuthToken = null)
    {
        if (isset($this->loaders[$label]) && is_callable($this->loaders[$label])) {
            $node = call_user_func($this->loaders[$label], $nodeId, $mimeFilter, $oAuthToken);
            if ($node instanceof NodeInterface) {
                return $node;
            }
        }
        throw new OutOfBoundsException("Unable to instantiate Node for '$label'.");
    }

} 