<?php

namespace NNT\ActivityLog\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use NNT\ActivityLog\ActivityLogStatus;
use Illuminate\Auth\AuthManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;

trait LogsActivity
{
    protected $enableLoggingModelsEvents = true;

    protected static function bootLogsActivity()
    {
        static::eventsToBeRecorded()->each(function ($eventName) {
            return static::$eventName(function (Model $model) use ($eventName) {
                if (!$model->shouldLogEvent($eventName)) {
                    return;
                }


                if ($eventName == '') {
                    return;
                }

                $beforeValue = [];
                $afterValue = $model->getAfterValue();
                if ($eventName == 'updated') {
                    $beforeValue = $model->getBeforeValue($model);
                }

                if ($eventName == 'deleted') {
                    $beforeValue = $model->getAttributes();
                }
                $user = $model->getCurrentUserChange();
                $description = $model->getDescription($eventName);

                activity_log()
                    ->logType($eventName)
                    ->causedBy($user)
                    ->performedOn($model)
                    ->beforeValue($beforeValue)
                    ->afterValue($afterValue)
                    ->log($description);
            });
        });
    }

    /*
     * Get the event names that should be recorded.
     */
    protected static function eventsToBeRecorded(): Collection
    {
        if (isset(static::$recordEvents)) {  // Chỉ định các event muốn log
            return collect(static::$recordEvents);
        }

        $events = collect([
            'created',
            'updated',
            'deleted',
        ]);

        if (collect(class_uses_recursive(static::class))->contains(SoftDeletes::class)) {
            $events->push('restored');
        }
        return $events;
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        $logStatus = app(ActivityLogStatus::class);
        if (!$this->enableLoggingModelsEvents || $logStatus->disabled()) {
            return false;
        }

        if (!in_array($eventName, ['created', 'updated'])) {
            return true;
        }
        if (Arr::has($this->getDirty(), 'deleted_at')) {  // getDirty trả về attribute đã thay đổi và chua được lưu trong db
            if ($this->getDirty()['deleted_at'] === null) {
                return false;
            }
        }

        // không log sự kiện update cho các attribute ignored
        return (bool) count(Arr::except($this->getDirty(), $this->attributesToBeIgnoredTriggerLogs()));
    }

    public function attributesToBeIgnoredTriggerLogs(): array
    {
        if (!isset(static::$ignoreChangedAttributes)) {
            return [];
        }

        return static::$ignoreChangedAttributes;
    }

    public function attributesToBeIgnoredInValue(): array
    {
        if (!isset(static::$logAttributesToIgnore)) {
            return [];
        }

        return static::$logAttributesToIgnore;
    }

    public function relatedType()
    {
        if (!isset(static::$relatedType)) {
            return '';
        }

        return static::$relatedType;
    }

    public function getDefaultGuard()
    {
        return app(AuthManager::class)->getDefaultDriver();
    }

    public function getBeforeValue()
    {
        $beforeValue = [];
        $attributes = array_keys($this->getChanges());
        $logAttributesToIgnores = $this->attributesToBeIgnoredInValue();

        if (count($attributes) > 0) {
            foreach ($attributes as $key) {
                if (!in_array($key, $logAttributesToIgnores)) {
                    $beforeValue[$key] = $this->getOriginal($key);
                }
            }
        }
        return $beforeValue;
    }

    public function getAfterValue()
    {
        $afterValue = $this->getChanges(); // trả về attribute đã thay đổi và đã được lưu trong db
        $logAttributesToIgnores = $this->attributesToBeIgnoredInValue();
        if (count($afterValue) == 0) {
            $afterValue = $this->getDirty(); // trả về attribute đã thay đổi và chua được lưu trong db
        }

        $afterValue = $this->convertPathName($afterValue);

        return Arr::except($afterValue, $logAttributesToIgnores);
    }

    public function getCurrentUserChange()
    {
        return auth()->guard($this->getDefaultGuard())->user();
    }

    public function getDescription($eventName)
    {
        if (method_exists($this, 'customActivityDescription')) {
            $description = $this->customActivityDescription($this, $eventName);
        }

        if (empty($description)) {
            $subject = Str::singular(Str::studly($this->getTable()));
            $description = __("activity_log::messages.{$eventName}", ['subject' => $subject]);
        }
        return $description;
    }

    public function convertPathName($afterValue) {
        return array_map(function ($item) {
            if ($item instanceof File) {
                return $item->getPathname();
            }
            return $item;
        }, $afterValue);
    }
}
