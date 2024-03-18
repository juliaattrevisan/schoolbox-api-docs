<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Schoolbox\HeaderSearch;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Exception;

class Controller extends Base
{
    /**
     * Returns a JSON array of users based on keyword
     *
     * @api /user/search
     */
    public function search(): void
    {
        $keyword = $this->requestQuery['keyword'];
        $result = [];

        if ($keyword !== null) {
            try {
                /** @var IConnector $db */
                $db = $this->di->get('databaseInternal');
                $userSearchService = new Search\Query($db, $this->getConfigLoader(), $this->getDi()->get('l10n'));
                $result = $userSearchService->fetchResults($keyword, $this->getUser());
            } catch (Exception $exception) {
                $result = [
                    'message' => $exception->getMessage()
                ];
            }
        }

        $this->response = $this->responseAsJson($result);
    }
}
