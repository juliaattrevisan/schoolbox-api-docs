<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\BulkImport;

use Alaress\Web\Controller\Base;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Pastoral;

/**
 * Module for bulk importation of pastoral records.
 */
class Controller extends Base
{
    /**
     * Constructor
     *
     * @param RosettaStone            $translator            Required for translating values used in the page title.
     * @param Pastoral\Mapper         $pastoralMapper        Required for creating pastoral records.
     * @param Pastoral\Type\Mapper    $pastoralTypeMapper    Required for loading and validating pastoral types.
     * @param Pastoral\SubType\Mapper $pastoralSubTypeMapper Required for loading and validating pastoral subtypes.
     */
    public function __construct(
        private RosettaStone $translator,
        private Pastoral\Mapper $pastoralMapper,
        private Pastoral\Type\Mapper $pastoralTypeMapper,
        private Pastoral\SubType\Mapper $pastoralSubTypeMapper,
    ) {
    }

    /**
     * Display form for creating a pastoral bulk import.
     *
     * @api GET /pastoral/bulk-import
     * @since 24.1
     * @return void
     */
    public function getForm(): void
    {
        $this->response = $this->responseFromTemplate(
            '@pastoralBulkImport/form.twig',
            [
                'types'    => $this->pastoralTypeMapper->findAll(),
                'subTypes' => $this->pastoralSubTypeMapper->findAll(),
            ],
            $this->translator->translate('pastoral', capital: true) . ' Bulk Upload',
        );
    }
}
