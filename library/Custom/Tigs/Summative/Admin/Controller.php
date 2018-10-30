<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Admin;

use Alaress\Web;

class Controller extends Web\Controller\Base
{
    /**
     * Renders the module admin page
     *
     * @api   GET /adminv2/custom/tigs/summative
     * @level 18.1.0
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getAdmin(): void
    {
        $this
            ->getResponse()->content
            ->set($this->buildHtmlOutput('@tigsSummativeAdmin/admin.twig', [])
            ->setTitle("TIGS Summative Reporting Admin"));
    }

    /**
     * Updates admin items available on the module admin page
     *
     * @api   POST /adminv2/custom/tigs/summative
     * @level 18.1.0
     */
    public function postAdmin(): void
    {

    }
}
