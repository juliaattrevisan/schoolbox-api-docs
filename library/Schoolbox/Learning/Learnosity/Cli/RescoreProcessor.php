<?php

namespace Schoolbox\Learning\Learnosity\Cli;

use LearnositySdk\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\ApiException;

class RescoreProcessor
{
    /**
     * Constructor.
     *
     * @param Api                  $api
     * @param bool                 $perform
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        private Api $api,
        private bool $perform,
        private ?LoggerInterface $logger = null
    ) {
    }


    /**
     * Mutate attempts in learnosity
     *
     * @param GenericCollection $attempts
     * @return void
     * @throws ApiException
     * @throws \JsonException
     * @throws ValidationException
     */
    public function process(\Schoolbox\GenericCollection $attempts): void
    {
        /* A counter for attempts
         */
        $numAttempts = $attempts->count();
        $i = 0;

        foreach ($attempts as $attempt) {
            $i++;

            $this->logger?->info('');

            /* Legacy attempts might not have a learnosity reference;
             * skip those and keep going (this happens in vagrants)
             */
            if ('' === trim((string)$attempt->getLearnosityReference())) {
                $this->logger?->info(sprintf(
                    '(%s/%s) Attempt %s does not have a learnosity reference; skipping',
                    $i,
                    $numAttempts,
                    $attempt->getId()
                ));

                continue;
            }

            $this->logger?->info(sprintf(
                '(%s/%s) Preparing attempt %s (%s) for rescoring',
                $i,
                $numAttempts,
                $attempt->getId(),
                $attempt->getLearnosityReference()
            ));

            /* Find the learnosity Responses for this $attempt as each attempt's
             * contents might actually vary between users
             *
             * This can happen when your item list isn't the same across Sessions
             * such as when you add or remove questions, and students' attempt
             * consequently vary in their content
             *
             * Such a circumstance gives you http 400 "Check session_ids - target_item not used: b985ec0c....."
             * but there's nothing we can do about that
             */
            $itemResponses = $this->api->dataGetRequest('/sessions/responses', [
                'session_id' => [
                    $attempt->getLearnosityReference()
                ]
            ])['data'][0]['items'] ?? null;

            /* No items? No trabajo
             */
            if (null === $itemResponses) {
                $this->logger?->info(sprintf(
                    '(%s/%s) No items found for attempt %s; skipping',
                    $i,
                    $numAttempts,
                    $attempt->getLearnosityReference(),
                ));

                continue;
            }

            /* Collate a set of item references for this attempt
             * Tell learnosity about the ones we need to rescore
             */
            $this->logger?->info(sprintf(
                '(%s/%s) Sending %s item(s) to learnosity for rescoring',
                $i,
                $numAttempts,
                count($itemResponses)
            ));

            /* A counter for items in this attempt
             */
            $numItemResponses = count($itemResponses);
            $j = 0;

            foreach ($itemResponses as $itemResponse) {
                $j++;

                $payload = [
                    'session_ids' => [
                        $attempt->getLearnosityReference()
                    ],
                    'target_item' => [
                        'reference' => $itemResponse['reference']
                    ]
                ];

                $this->logger?->debug(sprintf(
                    '(%s/%s) [%s/%s] Sending item %s to learnosity for rescoring',
                    $i,
                    $numAttempts,
                    $j,
                    $numItemResponses,
                    $itemResponse['reference']
                ));

                /* Send it to learnosity
                 */
                if ($this->perform) {
                    try {
                        $job = $this->api->dataUpdateRequest('/sessions/item', $payload);

                        $this->logger?->info(sprintf(
                            '(%s/%s) [%s/%s] Rescoring request made to learnosity and returned job_reference %s for item %s',
                            $i,
                            $numAttempts,
                            $j,
                            $numItemResponses,
                            $job['data']['job_reference'],
                            $itemResponse['reference']
                        ));
                    } catch (ApiException $e) {
                        $this->logger?->warning($e->getMessage());
                    }
                }
            }
        }
    }
}
