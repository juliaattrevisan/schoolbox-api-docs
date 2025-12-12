<?php

namespace Schoolbox\Learning\Assessment\DialogAgent\Response;

class TimeTaken
{
    /**
     * Find out how long a response has taken so far
     *
     * @param \Alaress_Date_Format $startedAt
     * @param int $allowedDuration
     * @return bool|null
     */
    public function hasExceeded(\Alaress_Date_Format $startedAt, int $allowedDuration): ?bool
    {
        return \Alaress_Date_Format::now()->diffInSeconds($startedAt) > $allowedDuration;
    }
}
