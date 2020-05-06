<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Realtime\Message;

use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper;

/**
 * Class SourceMapper
 *
 * @package Schoolbox\Realtime\Message
 */
class SourceMapper extends Mapper
{

    protected function mapping() {
        $this->hasTable('msg_source');
        $this->hasModel(Source::class);

        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'namespace',
            'property'   => 'namespace',
            'type'       => self::TYPE_MODEL,
            'join'       => 'namespace'
        ));
        $this->hasMap(array(
            'column'     => 'key',
            'property'   => 'key',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'role_type',
            'property'   => 'roleType',
            'type'       => self::TYPE_STRING,
        ));
        $this->hasMap(array(
            'column'     => 'description',
            'property'   => 'description',
            'type'       => self::TYPE_STRING,
        ));

        $this->hasJoin('namespace', array(
            'mapper'    => '\\Schoolbox\\Realtime\\Message\\NSMapper',
            'this_key'  => 'namespace',
            'other_key' => 'id',
        ));
    }


    /**
     * Returns a Collection of sources found in a namespace, looked up by slug
     *
     * @param string $slug
     * @return Collection|Source[]
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function findByNamespaceSlug($slug) {

        $nsJoin = $this->getJoinMapper('namespace');

        $stmt = $this->database->prepare("
            SELECT	{$this->getColumnString('src')},
                    {$nsJoin->getColumnString('ns')}
            FROM {$this->getTable()} `src`
            LEFT JOIN {$this->getJoinSql('namespace', 'src', 'ns')}
            WHERE ns.slug = :slug
        ");

        $stmt->execute(array('slug' => $slug));

        $collection = $this->createCollection();

        while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var Source $entity */
            $entity = $this->hydrate($r, 'src');
            $entity->setNamespace($nsJoin->hydrate($r, 'ns'));
            $collection->add($entity);
        }

        return $collection;
    }


    /**
     * Override so we can implicitly search on namespace.slug
     *
     * @param array $columnList
     * @param array $options
     * @return Collection
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     */
    public function findByColumnList($columnList, $options = array())
    {
        // enforce nocache
        $options['cache'] = false;

        // cast slug to namespace ID
        if (isset($columnList['slug'])) {
            $slug = $columnList['slug'];
            unset($columnList['slug']);
            $columnList['namespace'] = $this->getJoinMapper('namespace')->findOneByColumn('slug', $slug)->getId();
        }

        return parent::findByColumnList($columnList, $options);
    }

}
