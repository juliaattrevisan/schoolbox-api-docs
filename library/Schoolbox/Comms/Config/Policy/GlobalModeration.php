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
    protected $label = 'Global Moderation';

    /**
     * Twig template to represent controls in writable config screen
     * Also, the input name
     *
     * (you can't and shouldn't easily mix things up inadvertently)
     *
     * @var string
     */
    protected $identifier = 'policy-global-moderation';

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

