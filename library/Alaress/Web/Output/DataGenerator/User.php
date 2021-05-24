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
     * @inheritdoc
     */
    public function generate(?array $chromeData = []): array
    {
        $previousData = $this->session->get('previous');
        $this->session->set('no_header', $this->session->get('no_header') ?: isset($_GET['no_header']));

        return [
            'current'        => $this->currentUser,
            'previous'       => $previousData['user'] ?? null,
            'impersonated'   => (new ImpersonationCheck())->isImpersonated($this->currentUser, $previousData),
            'communityLogin' => $this->session->get('communityLogin'),
            'no_header'      => $this->session->get('no_header'),  //Hides top nav header if user session var set
        ];
    }
}
