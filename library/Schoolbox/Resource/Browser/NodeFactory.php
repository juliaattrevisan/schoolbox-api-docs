<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser;

use OutOfBoundsException;
use Schoolbox\Resource\Browser;
use Schoolbox\Resource\Browser\Office365\Tenant;

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

        $this->add(self::OFFICE, function ($nodeId, $mimeFilter = null, $tenant = null, $oAuthToken = null) {
            return new Browser\Office365\Node($nodeId, $mimeFilter, $tenant, $oAuthToken);
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
     * @param string      $label
     * @param int         $nodeId
     * @param string|null $mimeFilter
     * @param Tenant|null $tenant
     * @param string|null $oAuthToken
     * @return NodeInterface
     * @throws OutOfBoundsException
     */
    protected function buildNode($label, $nodeId, $mimeFilter = null, $tenant = null, $oAuthToken = null)
    {
        if (isset($this->loaders[$label]) && is_callable($this->loaders[$label])) {
            $node = call_user_func($this->loaders[$label], $nodeId, $mimeFilter, $tenant, $oAuthToken);
            if ($node instanceof NodeInterface) {
                return $node;
            }
        }
        throw new OutOfBoundsException("Unable to instantiate Node for '$label'.");
    }


    /**
     * @param string      $label
     * @param int         $nodeId
     * @param string|null $mimeFilter
     * @return NodeInterface
     * @throws OutOfBoundsException
     */
    public function build($label, $nodeId, $mimeFilter = null)
    {
        return $this->buildNode($label, $nodeId, $mimeFilter);
    }


    /**
     * @param string      $label
     * @param int         $nodeId
     * @param Tenant      $tenant
     * @param string      $oAuth
     * @param string|null $mimeFilter
     * @return NodeInterface
     * @throws OutOfBoundsException
     */
    public function buildOAuth($label, $nodeId, $tenant, $oAuth, $mimeFilter = null)
    {
        return $this->buildNode($label, $nodeId, $mimeFilter, $tenant, $oAuth);
    }
}
