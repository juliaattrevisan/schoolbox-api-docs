<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\Bridge;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Resource\Folder;
use UnexpectedValueException;
use function preg_match;

/**
 * Responsible for consistently providing the link between a Canvas Account and a Schoolbox Folder.
 */
class Account
{

    /** @var IConnector */
    private $schoolboxDb;


    /**
     * @param IConnector $schoolboxDb
     */
    public function __construct(IConnector $schoolboxDb)
    {
        $this->schoolboxDb = $schoolboxDb;
    }


    /**
     * Returns a value to prefix to the canvas id.
     *
     * @return string
     */
    private static function canvasIdPrefix(): string
    {
        return 'canvas-account';
    }


    /**
     * Return a predicate that can find a Schoolbox folder for specified Canvas Account
     *
     * @param int         $accountCanvasId
     * @param string|null $folderTableAlias
     * @return Builder
     * @throws InvalidColumnException
     */
    public function equalsAccountFolder(int $accountCanvasId, string $folderTableAlias = null): Builder
    {
        return (new Builder($this->schoolboxDb, $folderTableAlias ?? 'folder'))
            ->regex('name', "^.+\\({$this::canvasIdPrefix()}-{$accountCanvasId}\\)$");
    }


    /**
     * Returns a predicate that will match any Schoolbox folder that represents a Canvas Account
     *
     * @param string $folderTableAlias
     * @return Builder
     * @throws InvalidColumnException
     */
    public function equalsAnyAccountFolder(string $folderTableAlias = 'folder'): Builder
    {
        return (new Builder($this->schoolboxDb, $folderTableAlias))
            ->regex('name', "^.+\\({$this::canvasIdPrefix()}-\\d+\\)$");
    }


    /**
     * Returns a Canvas `account`.`canvas_id` from Schoolbox folder
     *
     * @param Folder\Model $folder
     * @return int
     * @throws UnexpectedValueException
     */
    public function getAccountCanvasIdFromFolder(Folder\Model $folder): int
    {
        $folderName = $folder->getName();
        if (preg_match("/^.+\({$this::canvasIdPrefix()}-(\d+)\)$/", $folderName, $matches)) {
            return (int)$matches[1];
        }
        throw new UnexpectedValueException("Unable to determine `account.canvas_id` from folder name '$folderName'");
    }


    /**
     * Consistently generate a Schoolbox folder name for a Canvas Account
     *
     * @param string $accountName
     * @param string $accountCanvasId
     * @return string
     */
    public function generateLinkedFolderName(string $accountName, string $accountCanvasId): string
    {
        return "$accountName ({$this::canvasIdPrefix()}-{$accountCanvasId})";
    }
}
