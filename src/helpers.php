<?php

use NNT\ActivityLog\ActivityLogger;
use NNT\ActivityLog\ActivityLogStatus;

if (! function_exists('activity_log')) {
    function activity_log(): ActivityLogger
    {
        $logStatus = app(ActivityLogStatus::class);
        return app(ActivityLogger::class)
            ->setLogStatus($logStatus);
    }
}
