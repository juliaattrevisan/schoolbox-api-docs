<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\DifferentiationProfile;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param RosettaStone $rosettaStone
     * @param ErrorMapper $errorMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(
        private RosettaStone $rosettaStone,
        private ErrorMapper $errorMapper,
        private UserMapper $userMapper,
    ) {
    }


    /**
     * Provide form
     *
     */
    public function manageDifferentiationProfiles(): void
    {
        $translation = $this->rosettaStone->translate('differentiationProfile', true, true);
        $this->response = $this->responseFromTemplate(
            '@differentiationProfiles/index.twig',
            [
            ],
            "Manage $translation"
        );
    }

//    public function


    /**
     * Process the form vars
     */
//    public function postExample(): void
//    {
//        try {
//            $user = $this->userMapper->findById($this->requestPost['id']);
//        } catch (MapperException $e) {
//            throw $this->errorMapper->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
//        }
//
//        $this->response = $this->responseFromTemplate(
//            '@example/submit.twig',
//            [
//                'user' => $user,
//                'var1' => $this->requestPost['var1'],
//            ],
//            'Example Form Output',
//        );
//    }
}
