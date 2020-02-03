<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint;

use RuntimeException;
use Schoolbox\External\Importer\Endpoint\Specification\CliRenderer;

/**
 * Class Specification
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint
 */
abstract class Specification
{

    /**
     * @var Batchxml
     */
    protected $batchxml;


    /**
     * @var array
     */
    protected $paths = [];


    /**
     * Specification constructor.
     *
     * @param Batchxml $batchxml
     */
    public function __construct(Batchxml $batchxml)
    {
        $this->batchxml = $batchxml;
    }


    /**
     * Get a list of nodes that did, or didn't, get configured properly
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function check(): array
    {
        $endpoints = [];

        /* No paths, no trabajo
         */
        if (!\count($this->paths)) {
            return $endpoints;
        }

        foreach ($this->paths as $path) {
            if ($this->batchxml->fetchXml($path)) {
                $endpoints[] = [
                    'message' => $path,
                    'status' => CliRenderer::STATUS_OK
                ];
            } else {
                $endpoints[] = [
                    'message' => $path,
                    'status' => CliRenderer::STATUS_MISSING
                ];
            }
        }

        return $endpoints;
    }
}
