<?php

/**
 * Created by PhpStorm.
 * User: jleckie
 * Date: 20/08/15
 * Time: 2:51 PM
 */

namespace Schoolbox\Engagement;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Database\Factory;

class News
{
    /**
     * @var IConnector
     */
    private IConnector $database;

    /**
     * Constructor - sets up database
     */
    public function __construct(IConnector $database)
    {
        $this->database = $database;
    }

    public function schoolCreated(\Alaress_Date_Format $start, \Alaress_Date_Format $end)
    {
        $query = "SELECT COUNT(*) FROM comms_news_article
                  WHERE created_at >= :start AND created_at <= :end";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchColumn(0);
    }

    public function groupCreated(\Alaress_Date_Format $start, \Alaress_Date_Format $end)
    {
        $query = "SELECT COUNT(*) FROM comms_news_article
                  JOIN comms_news_audience cna on comms_news_article.id = cna.comms_news_article_id AND entity_type = 'folder'
                  WHERE created_at >= :start AND created_at <= :end";

        $statement = $this->database->prepare($query);
        $statement->bindParam(':start', $start->getDatabaseDate());
        $statement->bindParam(':end', $end->getDatabaseDate());

        $statement->execute();
        return $statement->fetchColumn(0);
    }
}
