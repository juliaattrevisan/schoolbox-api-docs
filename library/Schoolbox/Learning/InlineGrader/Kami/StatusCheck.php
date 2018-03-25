<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

/**
 * Responsible for checking the status of a processing kami
 * document to determine if it is now 'done' or 'failed'
 */
class StatusCheck
{

    /** @var Mapper */
    private $mapper;

    /** @var IConnector */
    private $connector;


    /**
     * @param Mapper     $mapper
     * @param IConnector $connector
     */
    public function __construct(Mapper $mapper, IConnector $connector)
    {
        $this->mapper    = $mapper;
        $this->connector = $connector;
    }


    /**
     * Checks the Kami API to see if a 'processing' document is finished
     *
     * @param Model $model
     * @return Model
     * @throws Exception\ApiException If Kami returns a response we cannot understand
     * @throws Exception\EmptyApiKeyException If Kami is not enabled
     * @throws Exception\NotFound If Kami doc not found in Kami API
     * @throws Exception\ResponseDecodeException If cannot decode the Kami response
     * @throws \LogicException If model is not processing
     * @throws \PDOException If unable to save model to DB
     * @throws \UnexpectedValueException If unexpected status change is reported
     */
    public function updateStatus(Model $model): Model
    {
        if (!$model->isStatusProcessing()) {
            throw new \LogicException(sprintf(
                "Only '%s' status can change. Found: '%s'",
                Model::STATUS_PROCESSING,
                $model->getStatus()
            ));
        }
        $kamiDoc = $this->connector->getDocument($model->getKamiDocumentId());

        // No change?
        if ($kamiDoc->isProcessing()) {
            return $model;
        }

        // Update Schoolbox if a change detected in status
        if ($kamiDoc->isDone()) {
            $model->setStatusDone();
        } elseif ($kamiDoc->isError()) {
            $model->setStatusError();
            $model->setErrorMessage($kamiDoc->getFileErrorMessage());
        } else {
            throw new \UnexpectedValueException("Unexpected document status: {$kamiDoc->getFileStatus()}");
        }
        $this->mapper->save($model);
        return $model;
    }
}
