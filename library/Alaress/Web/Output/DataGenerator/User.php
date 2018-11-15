<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Alaress\Session\Session;
use Schoolbox\User as UserModel;

/**
 * Generate data for rendering the current user, and the user who's
 * impersonating them (if anyone)
 */
class User implements IDataGenerator
{
    /**
     * @var null|UserModel
     */
    private $currentUser;


    /**
     * @var Session
     */
    private $session;


    /**
     * Constructor
     *
     * @param null|UserModel $currentUser
     * @param Session        $session
     */
    public function __construct(?UserModel $currentUser, Session $session)
    {
        $this->currentUser = $currentUser;
        $this->session     = $session;
    }


    /**
     * @return array
     */
    public function generate(): array
    {
        $previousUser = null;
        $impersonated = false;
        if ($this->currentUser) {
            $previousData = $this->session->get('previous');
            $previousUser = $previousData['user'] ?? null;
            if ($previousUser instanceof UserModel) {
                if ($previousUser->isSuperuser()) {
                    $impersonated = true;
                }
            } else {
                $previousUser = null;
            }
        }
        return [
            'current'      => $this->currentUser,
            'previous'     => $previousUser,
            'impersonated' => $impersonated,
        ];
    }
}
