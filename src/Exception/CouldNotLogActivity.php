<?php

namespace NNT\ActivityLog\Exception;

use Exception;

class CouldNotLogActivity extends Exception
{
    public static function couldNotDetermineUser($id)
    {
        return new static("Could not determine a user with identifier `{$id}`.");
    }
}
