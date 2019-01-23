<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Test;

/**
 * Generate test data for testing discussion threads.
 *
 * Obviously, don't run this on a live server.
 */
class DataGenerator
{

    public function generateTestData(int $seed = null): void
    {
        if (isset($seed)) {
            mt_srand($seed);
        }

        // Clear out all existing comms articles

        // Clear out all existing users? No, just add some new ones

        // Add some possible audiences for comms articles (and add users to them):
        // * Campus
        // * Classes/Groups
        // * Roles
        // * Houses
        // * Year levels

        // Create some comms articles, for a random audience each time
    }
}
