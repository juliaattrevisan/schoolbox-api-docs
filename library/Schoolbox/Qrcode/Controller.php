<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Qrcode;

use Alaress\Web\Controller\Base;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Class Controller
 *
 * @package Schoolbox\Qrcode
 */
class Controller extends Base
{

    /**
     * Controller constructor.
     */
    public function __construct()
    {
    }


    /**
     * QR Code generation page
     *
     * @api   GET /qrcode/:folderId
     * @since 22.0
     * @param Folder $folder
     */
    public function getListPage(Folder $folder): void
    {
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@qrcode/list.twig',
                ['folder' => $folder],
                'QR Codes'
            )
        );
    }
}
