<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Intellischool;

use Alaress\Web\Controller\Base;
use Schoolbox\User;

/**
 * Controller for the Intellischool integration.
 */
class Controller extends Base
{
    /**
     * Factory for creating JWTs for sending to Intellischool.
     */
    private JwtFactory $jwtFactory;

    /**
     * Constructor
     *
     * @param JwtFactory $jwtFactory
     */
    public function __construct(JwtFactory $jwtFactory)
    {
        $this->jwtFactory = $jwtFactory;
    }

    /**
     * Index page for the Intellischool integration.
     *
     * @api GET /intellischool
     * @since 21.1.0
     */
    public function getIndex(): void
    {
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@intellischool/index.twig',
                [
                    'idToken' => $this->jwtFactory->buildForLearnerProfile(
                        $this->getUser(),
                        (new User())->setExternalId('1014'),
                    )
                ],
            )
        );
    }
}
