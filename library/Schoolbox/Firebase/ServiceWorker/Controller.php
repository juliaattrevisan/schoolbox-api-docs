<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Firebase\ServiceWorker;

use Alaress\Web\Controller\Base;

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
        $this->response = $this
            ->responseFromTemplateUnthemed('@firebaseServiceWorker/service-worker.js.twig', [])
            ->withHeader('Content-Type', 'application/javascript')
        ;
    }
}
