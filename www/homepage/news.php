<?php

/**
 * DEPRECATED: This redirects the old homepage news article links to the new system
 * This can be removed in 2019
 */

// Temporary redirect to the new system

/** @var SitePage $this */

if(isset($this->arg[1]) && is_numeric($this->arg[1])) {
    /** @var \Alaress\Database\Connector\IConnector $db */
    $db = $this->getDi()->get('databaseInternal');
    $article_id = $db->executeQuery("SELECT migration_id FROM subject_news WHERE id = ?", [$this->arg[1]])->fetchColumn();
    $this::localRedir('/news/' . $article_id);
} else {
    $this->pageNotFound();
}
