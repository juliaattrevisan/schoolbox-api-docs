<?php
/**
 * Factory for building external database sync classes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2016 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External\Connector;

use Schoolbox\IConfigLoader;

class CommunityPortalFactory
{
    /**
     * @var IConfigLoader
     */
    protected $config;

    /**
     * Constructor
     *
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }

    /**
     * Choose which variant to spawn
     *
     * @return CommunityPortalInterface
     * @throws \UnexpectedValueException if nothing can be built
     */
    public function build()
    {
        $connector               = ucwords($this->config->get('external_type'));
        $synergeticPasswordReset = $this->config->get('synergetic_password_reset');
        $webServicesKeyFile      = '/etc/schoolbox-synergetic/config.json';

        // Synergetic only
        if ($connector === 'Synergetic' && $synergeticPasswordReset) {
            $key = false;
            $iv  = false;
            if (is_readable($webServicesKeyFile) && is_file($webServicesKeyFile)) {
                $config = json_decode(file_get_contents($webServicesKeyFile), true);
                $key    = base64_decode($config['key']);
                $iv     = base64_decode($config['iv']);
            } else {
                // can't read file, that's a warning at least.
                trigger_error('Cannot read community portal key - password will not be changed', E_USER_WARNING);
            }
            return new Synergetic\Base\CommunityPortal($key, $iv, $this->config->get('synergetic_portal_create_account'));
        }
        return new Offline\Base\CommunityPortal();
    }
}
