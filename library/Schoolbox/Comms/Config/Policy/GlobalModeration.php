<?php
/**
 * GlobalModeration
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\Config\Policy;

class GlobalModeration
{

    /**
     * Policy name
     *
     * @var string
     */
    private $label = 'Global Moderation';

    /**
     * Twig template to represent controls in writable config screen
     *
     * @var string
     */
    private $template = 'policy-global-moderation';

    /**
     * This configuration is on (active)
     *
     * @var bool
     */
    private $isActive = false;

    /**
     * Strings to describe states
     *
     * @var array
     */
    private $options = [
        'active' => [
            'state' => 'Global moderation is on',
            'description' => 'Every article requires moderator approval.'
        ],
        'inactive' => [
            'state' => 'Global moderation is off',
            'description' => 'You can freely post articles.'
        ]
    ];

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }


    /**
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }


    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }


    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}

