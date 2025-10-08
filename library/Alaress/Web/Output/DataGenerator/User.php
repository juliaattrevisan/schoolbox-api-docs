<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Alaress\Session\Session;
use Alaress\Session\ImpersonationCheck;
use Schoolbox\User as UserModel;

/**
 * Generate data for rendering the current user, and the user who's
 * impersonating them (if anyone)
 */
class User implements IDataGenerator
{
    /**
     * Constructor
     *
     * @param null|UserModel $currentUser
     * @param Session        $session
     */
    public function __construct(
        private ?UserModel $currentUser,
        private Session $session,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function generate(?array $chromeData = []): array
    {
        $initialUser  = $this->session->get(ImpersonationCheck::SESSION_KEY_INITIAL_USER);
        $previousUser = $this->session->get(ImpersonationCheck::SESSION_KEY_PREVIOUS_USER);
        $this->session->set('no_header', $this->session->get('no_header') ?: isset($_GET['no_header']));
        $birthdaySession = $this->session->getCookie('birthdayCelebrated_' . $this->currentUser->getId());

        return [
            'current'           => $this->currentUser,
            'initial'           => $initialUser,
            'previous'          => $previousUser,
            'impersonated'      => (new ImpersonationCheck())->isImpersonated($this->currentUser, $previousUser),
            'communityLogin'    => $this->session->get('communityLogin'),
            'no_header'         => $this->session->get('no_header'),  //Hides top nav header if user session var set
            'celebrateBirthday' => !isset($birthdaySession) && ($this->session->get('isBirthdayCelebration') ?? false),
        ];
    }
}
