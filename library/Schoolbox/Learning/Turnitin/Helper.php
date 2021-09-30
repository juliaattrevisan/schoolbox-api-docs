<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\IConfigLoader;
use Schoolbox\User;

/**
 * A miscellaneous class that determines the state of Turnitin configuration
 * and accessibility to the current user based on roletype.
 */
class Helper
{

    /**
     * @var IConfigLoader
     */
    private IConfigLoader $configLoader;


    /**
     * Constructor.
     *
     * This probably should take the three params individually rather than the god-object
     * Config which is magically accessed
     *
     * @param IConfigLoader $configLoader
     */
    public function __construct(IConfigLoader $configLoader)
    {
        $this->configLoader = $configLoader;
    }


    /**
     * Decide if turnitin was configured adequately
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        /* If both of these are set, then Turnitin was configured presumably correctly
         * and should take precedence over plag
         */
        $host = trim((string)$this->configLoader->get('turnitin_api_host'));
        $key = trim((string)$this->configLoader->get('turnitin_api_key'));

        return '' !== $host && '' !== $key;
    }


    /**
     * If the turnitin host and key was set in config, and turnitin is enabled for the $user specified
     *
     * Assuming this is the case, the following policies are obeyed:
     * - Students and parents can see similarity results allowed via config
     * - Staff and Superusers can always see similarity results
     *
     * @param User $user
     * @return bool
     */
    public function isEnabled(User $user): bool
    {
        /* If both of these are set, then Turnitin was configured presumably correctly
         * and should take precedence over plag
         */
        if ($this->isConfigured()) {
            /* Staffs and super can always see similarity results
             */
            if ($user->isSuperuser() || $user->getRole()->isStaff()) {
                return true;
            }

            /* If the $user is a student or a parent, check a config var too
             */
            if ($this->configLoader->get('similarity_check_student_visible') && ($user->getRole()->isStudent() || $user->getRole()->isParent())) {
                return true;
            }
        }

        return false;
    }
}