<?php

namespace Schoolbox\APIAssessment;

class Helper
{

    public function castToBool(string $input): bool
    {
        if ('0' === $input) {
            return false;
        } else {
            return true;
        }
    }
}
