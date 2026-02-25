<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Wiki;

use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class RevisionMapper
 *
 * @package Schoolbox\Wiki
 * @method Revision hydrate(array $row, $alias = '', $callback = null)
 * @method Revision findById($id)
 * @method Revision createModel()
 */
class RevisionMapper extends Mapper implements FeedableMapperInterface
{
    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('wikiRevision');
        $this->hasModel(Revision::class);
        $this->uses('revisionID', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins('wikiID', WikiMapper::class, 'wikiID', null, null, 'wiki', 'wiki');
        $this->uses('heading', self::TYPE_STRING);
        $this->uses('body', self::TYPE_STRING);
        $this->joins('userID', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('created', self::TYPE_DATETIME);
    }

    /**
     * @param int $categoryId
     * @param int $limit
     * @param int $offset
     * @param int $time
     *
     * @return Collection|Revision[]
     */
    public function findForFeed($categoryId, $limit, $offset = 0, $time = 0)
    {
        $time   = date('Y-m-d H:i:s', $time ? : time());
        $limit  = (int)$limit;
        $offset = (int)$offset;
        $stmt   = $this->database->prepare("
            {$this->getSelectString()}
            WHERE
                `c`.`id` = :cat_id
                AND `w`.`deleted` = 'false'
                AND `wikiRevision`.`created` < :time
            ORDER BY `wikiRevision`.`created` DESC
            LIMIT $offset, $limit
        ");
        $stmt->execute(
            array(
                'cat_id' => $categoryId,
                'time'   => $time
            )
        );

        return $this->hydrateSelect($stmt);
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('wiki', 'w')},
                {$this->getJoinMapper('wiki')->getJoinColumnString('category', 'c')},
                {$this->getJoinColumnString('user', 'u')},
                {$this->getJoinColumnString('user.pronoun', 'up')}
            FROM
                `{$this->getTable()}`
                INNER JOIN {$this->getJoinSql('wiki', '', 'w', true)}
                LEFT JOIN {$this->getJoinMapper('wiki')->getJoinSql('category', 'w', 'c')}
                INNER JOIN {$this->getJoinSql('user', '', 'u', true)}
            LEFT JOIN {$this->getJoinSql('user.pronoun', 'u', 'up')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $col = $this->createCollection();

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {
            $revision = $this->hydrate($r);
            $revision->setWiki($this->hydrateJoin($r, 'wiki', 'w'));
            $revision->getWiki()->setCategory($this->getJoinMapper('wiki')->hydrateJoin($r, 'category', 'c'));
            /** @var User $user */
            $user = $this->hydrateJoin($r, 'user', 'u');
            $user->setPronoun($this->hydrateJoin($r, 'user.pronoun', 'up'));
            $revision->setUser($user);

            $col->add($revision);
        }

        return $col;
    }


    /**
     * Figure out if there are any empty links - these are assumed to be stubs.
     * If a title attribute is provided, use that as the stub wiki heading
     *
     * @param string $body
     * @param int    $categoryId
     *
     * @return string
     */
    public function createStubLinks($body, $categoryId)
    {
        // DomDocument based parsing
//        $dom = new \DOMDocument();
//        $dom->loadHTML($body);
//        $xpath = new \DOMXPath($dom);
//        foreach ($xpath->query('//a[@title]') as $linkNode) {
//            /** @var \DOMElement $linkNode */
//            $title = trim($linkNode->getAttribute('title'));
//            if (strlen($title) === 0) {
//                continue;
//            }
//            $linkNode->removeAttribute('title');
//            $url = new \Url($title);
//            $linkNode->setAttribute('href', "/wiki/{$categoryId}/{$url->getSlug()}");
//        }
//        return $dom->saveHTML();
        preg_match_all('/<a title="([^"]*)">/', $body, $matches);
        if (!count($matches[1])) {
            return $body;
        }
        $pattern = array();
        $replace = array();
        foreach ($matches[1] as $i => $match) {
            // Try using title attribute as a heading, otherwise ignore creation of empty stubs
            if (!strlen(trim($match))) {
                continue;
            }
            // Generate a slug for a new article that won't exist
            $url       = new \Url($match);
            $slug      = $url->getSlug();
            $pattern[] = $matches[0][$i];
            $replace[] = "<a href='/wiki/{$categoryId}/{$slug}'>";
        }
        // Perform replacements if required
        if (count($pattern)) {
            $body = str_replace($pattern, $replace, $body);
        }
        return $body;
    }
}
