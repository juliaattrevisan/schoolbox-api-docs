<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Textbox\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Alaress\HTMLPurifier\IPurifier;
use Aura\Router\Router;
use Schoolbox\Resource\Textbox\Model;
use Schoolbox\User;

/**
 * Translator for translating a textbox model to an array.
 */
class ModelToArray
{
    use GetRouteIfAuthorized;

    /**
     * @var IPurifier
     */
    private IPurifier $purifier;

    /**
     * Constructor
     *
     * @param Router     $router
     * @param Authorizer $authorizer
     * @param User       $currentUser
     * @param IPurifier  $purifier
     */
    public function __construct(
        Router $router,
        Authorizer $authorizer,
        User $currentUser,
        IPurifier $purifier
    ) {
        $this->router      = $router;
        $this->authorizer  = $authorizer;
        $this->currentUser = $currentUser;
        $this->purifier    = $purifier;
    }

    /**
     * @param Model $textbox
     * @return array
     */
    public function __invoke(Model $textbox): array
    {
        return [
            'id'      => $textbox->getId(),
            'heading' => $textbox->getHeading(),
            'body'    => $this->purifier->purify($textbox->getBody()),
            '_links'  => [
                'update' => $this->getRouteIfAuthorized(
                    'textbox.updateTextbox',
                    [
                        'textbox' => $textbox,
                        'folder'  => $textbox->getCategory(),
                        'id'      => $textbox->getId(),
                    ]
                ),
                'delete' => $this->getRouteIfAuthorized(
                    'textbox.deleteTextbox',
                    [
                        'textbox' => $textbox,
                        'folder'  => $textbox->getCategory(),
                        'id'      => $textbox->getId(),
                    ]
                ),
            ],
        ];
    }
}
