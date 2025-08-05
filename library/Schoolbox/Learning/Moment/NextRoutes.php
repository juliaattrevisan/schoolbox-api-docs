<?php

namespace Schoolbox\Learning\Moment;

class NextRoutes
{
    /**
     * @param Evidence\Model $moment
     * @return array
     */
    public function generate(Evidence\Model $moment): array
    {
        /* You can't SOBO a learning moment, so this is always null for now:
         * That might change later
         */
        $submitLink = null;

        if ($moment->isSubmitted()) {
            /* Next mutable states: published, rejected
             */
            $publishLink = true;
            $rejectLink = true;
        } elseif ($moment->isPublished()) {
            /* Next mutable states: rejected
             */
            $publishLink = null;
            $rejectLink = true;
        } elseif ($moment->isRejected()) {
            /* Next mutable states: published
             */
            $publishLink = true;
            $rejectLink = null;
        } else {
            /* Next mutable states: published, rejected
             */
            $publishLink = true;
            $rejectLink = true;
        }

        return [
            'submit' => $submitLink,
            'publish' => $publishLink,
            'reject' => $rejectLink,
        ];
    }
}
