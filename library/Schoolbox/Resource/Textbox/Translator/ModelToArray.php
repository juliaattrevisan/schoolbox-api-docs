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
     * The folder translator, used to translate this model's folder.
     *
     * @var callable
     */
    private $folderTranslator;

    /**
     * Purifier instance, used to ensure that no non-purified textbox body
     * content is output.
     */
    private IPurifier $purifier;

    /**
     * Constructor
     *
     * @param callable   $folderTranslator,
     * @param Router     $router
     * @param Authorizer $authorizer
     * @param User       $currentUser
     * @param IPurifier  $purifier
     */
    public function __construct(
        callable $folderTranslator,
        Router $router,
        Authorizer $authorizer,
        User $currentUser,
        IPurifier $purifier
    ) {
        $this->folderTranslator = $folderTranslator;
        $this->router           = $router;
        $this->authorizer       = $authorizer;
        $this->currentUser      = $currentUser;
        $this->purifier         = $purifier;
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
            'folder'  => call_user_func($this->folderTranslator, $textbox->getCategory()),
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
