<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\BulkImport;

use Alaress\Web\Controller\Base;

/**
 * Module for bulk importation of pastoral records.
 */
class Controller extends Base
{
    /**
     * Display form for creating a pastoral bulk import.
     *
     * @api GET /pastoral/bulk-import
     * @since 24.1
     * @return void
     */
    public function getForm(): void
    {
        $this->response = $this->responseFromTemplate('@pastoralBulkImport/form.twig', []);
    }
}
