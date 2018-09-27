<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

use Schoolbox\Config\Config;
use Schoolbox\Admin\Settings\IConfigRenderer;

abstract class Base implements IConfigRenderer
{

    /** @var Config */
    private $config;


    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }


    /**
     * @return string[]
     */
    public function getData(): array
    {
        return [
            'name'        => $this->config->getName(),
            'value'       => $this->config->getValue(),
            'title'       => $this->config->getTitle(),
            'description' => $this->config->getDescription(),
            'required'    => $this->config->getRequired(),
            'editable'    => $this->config->getEditable(),
        ];
    }


    /**
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->config;
    }
}
