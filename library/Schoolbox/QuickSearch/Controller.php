<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Schoolbox\QuickSearch;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;

class Controller extends Base
{
    public function __construct()
    {
    }

    /**
     * Returns a JSON array of users based on keyword
     *
     * @api /user/search
     */
    public function search()
    {
        $keyword = $this->requestQuery['keyword'];
        $result = [];

        if ($keyword !== null) {
            try {
                /** @var IConnector $db */
                $db = $this->di->get('databaseInternal');
                $userSearchService = new Search\Query($db, $this->getConfigLoader(), $this->getDi()->get('l10n'));
                $result = $userSearchService->fetchResults($keyword, $this->getUser());
            } catch (\Exception $exception) {
                // noop
                $result = [
                    'message' => $exception->getMessage()
                ];
            }
        }

        $this->response = $this->responseAsJson($result);
    }
}
