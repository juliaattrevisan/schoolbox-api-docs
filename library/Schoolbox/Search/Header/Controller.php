<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Schoolbox\Search\Header;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Exception;

class Controller extends Base
{
    public const STATUS_OK = 200;
    public const STATUS_SERVER_ERROR = 500;

    /**
     * Returns a JSON array of users based on keyword
     *
     * @api GET /search/header
     */
    public function search(): void
    {
        $keyword = $this->requestQuery['keyword'];
        $result = [];
        $errorOccurred = false;

        if ($keyword !== null) {
            try {
                /** @var IConnector $db */
                $db = $this->di->get('databaseInternal');
                $userSearchService = new Search\Query($db, $this->getConfigLoader(), $this->getDi()->get('l10n'));
                $result = $userSearchService->fetchResults($keyword, $this->getUser());
            } catch (Exception $exception) {
                $errorOccurred = true;
                $result = [
                    'message' => $exception->getMessage()
                ];
            }
        }

        $this->response = $this->responseAsJson($result)->withStatus($errorOccurred ? self::STATUS_SERVER_ERROR : self::STATUS_OK);
    }
}
