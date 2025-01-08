<?php

namespace Schoolbox\L10n;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Provides the current locale as a JavaScript file that allows the frontend to use it
     *
     * This must be a file rather than a JSON endpoint since we need browsers to run this
     * before the rest of the application is loaded, otherwise the locale may not be available in time.
     * Browsers have no reliable way of creating a blocking fetch request.
     *
     * This file is cached for a day to avoid unnecessary requests.
     *
     * @api GET /l10n.js
     * @since 25.0
     *
     * @return void
     */
    public function getLocale()
    {
        $this->setAllowBackToTheFuture(false); // Don't store to the history
        $this->response = $this
            ->responseFromTemplateUnthemed('@l10n/l10n.js.twig', [])
            ->withHeader('Content-Type', 'application/javascript')
            ->withHeader('Pragma', ' ')
            ->withHeader('Cache-Control', 'public, max-age=86400') // 1 day
        ;
    }
}
