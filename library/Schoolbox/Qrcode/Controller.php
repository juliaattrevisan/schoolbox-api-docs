<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Qrcode;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * Class Controller
 *
 * @package Schoolbox\Qrcode
 */
class Controller extends Base
{

    /**
     * @var ErrorMapper
     */
    private ErrorMapper $errorMapper;


    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     */
    public function __construct(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }


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
