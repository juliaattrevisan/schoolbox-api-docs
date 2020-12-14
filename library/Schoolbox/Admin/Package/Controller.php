<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Package;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    private const PAGE_CURRENT = 'package';

    /**
     * @var Mapper
     */
    private Mapper $packageMapper;

    /**
     * @param Mapper $packageMapper
     */
    public function __construct(Mapper $packageMapper)
    {
        $this->packageMapper      = $packageMapper;
    }

    /**
     * Get index all available packages and their installation state
     *
     * @api   GET /adminv2/grades
     * @since 20.2
     */
    public function getIndex(): void
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@adminPackage/index.twig',
                [
                    'pageCurrent' => self::PAGE_CURRENT,
                    'packages' => $this->packageMapper->findAll(),
                ]
            )
        );
    }


    /**
     * Install a package
     *
     * @api   POST /adminv2/package
     * @since 20.2
     */
    public function postInstall(): void
    {

    }
}
