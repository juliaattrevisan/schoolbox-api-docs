<?php

/**
 * DEPRECATED: This redirects the old homepage news article links to the new system
 * This can be removed in 2019
 */

// Temporary redirect to the new system

/** @var SitePage $this */

var_dump($this->arg);
if(isset($this->arg[1]) && is_numeric($this->arg[1])) {
    $article_id = $this->db->getOne("SELECT migration_id FROM subject_news WHERE id = {$this->db->smartQuote($this->arg[1])}");
    $this->localRedir('/news/' . $article_id);
} else {
    $this->pageNotFound();
}
