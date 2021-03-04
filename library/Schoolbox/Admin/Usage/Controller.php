<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Usage;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;


/**
 * Class Controller
 *
 * @package Schoolbox\Admin\Usage
 */
class Controller extends Base
{

    /**
     * @var ErrorMapper
     */
    private $errorMapper;


    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     */
    public function __construct(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }


    /**
     * Provide form
     */
    public function getSystem(): void
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@adminUsage/system.twig', [
        ])->setTitle('System Usage'));
    }
}
