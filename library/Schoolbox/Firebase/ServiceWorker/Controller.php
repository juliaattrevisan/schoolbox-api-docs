<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Firebase\ServiceWorker;

use Alaress\Web\Controller\Base;
use Schoolbox\Firebase\DataGenerator;
use Schoolbox\MobileApp\ServiceMapper;

/**
 * Controller for the Firebase service worker.
 */
class Controller extends Base
{
    /**
     * Render the Firebase service worker.
     *
     * @api GET /firebase-messaging-sw.js
     * @since 24.0
     *
     * @return void
     */
    public function getServiceWorker(): void
    {
        $this->setAllowBackToTheFuture(false);
        $dataGenerator  = new DataGenerator(ServiceMapper::getInstance());
        $this->response = $this
            ->responseFromTemplateUnthemed(
                '@firebaseServiceWorker/service-worker.js.twig',
                ['firebase' => $dataGenerator->generate()],
            )
            ->withHeader('Content-Type', 'application/javascript')
            ->withHeader('Cache-Control', 'max-age=86400, private')
            ->withHeader('Pragma', '')
        ;
    }
}
