<?php
/**
 * GlobalModeration
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\Config\Policy;

class GlobalModeration extends Base
{

    /**
     * Policy name
     *
     * @var string
     */
    protected $name = 'Global Moderation';

    /**
     * The input element's name attribute
     *
     * @var string
     */
    protected $identifier = 'policy-global-moderation';

    /**
     * Config twig file
     *
     * @var string
     */
    protected $template = 'switch';

    /**
     * This configuration is on (active)
     *
     * @var bool
     */
    protected $isActive = false;

    /**
     * Strings to describe states
     *
     * @var array
     */
    protected $options = [
        'config' => 'Enable to require moderation on every article before they are made publicly available',
        'active' => [
            'state' => 'Global moderation is on',
            'description' => 'Every article requires moderator approval'
        ],
        'inactive' => [
            'state' => 'Global moderation is off',
            'description' => 'You can freely post articles'
        ]
    ];
}

