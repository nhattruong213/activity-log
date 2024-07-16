<?php

namespace NNT\ActivityLog;

use NNT\ActivityLog\Exception\CouldNotLogActivity;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use NNT\ActivityLog\ActivityLogStatus;
use NNT\ActivityLog\Models\ActivityLog;
use Illuminate\Support\Str;

class ActivityLogger
{

    /** @var \Illuminate\Auth\AuthManager */
    protected $auth;

    protected $defaultLogName = '';

    /** @var string */
    protected $authDriver;

    /** @var \NNT\ActivityLog\ActivityLogStatus */
    protected $logStatus;

    /** @var ActivityLog */
    protected $activity;

    public function __construct(AuthManager $auth, Repository $config, ActivityLogStatus $logStatus)
    {
        $this->auth = $auth;

        $this->authDriver = $config['activity-log']['default_auth_driver'] ?? $auth->getDefaultDriver();

        $this->logStatus = $logStatus;
    }

    public function setLogStatus(ActivityLogStatus $logStatus)
    {
        $this->logStatus = $logStatus;

        return $this;
    }

    public function logType(string $logType)
    {
        $this->getActivity()->log_type = $logType;

        return $this;
    }

    public function beforeValue($values)
    {
        $this->getActivity()->before_value = json_encode($values);

        return $this;
    }

    public function afterValue($values)
    {
        $this->getActivity()->after_value = json_encode($values);

        return $this;
    }

    public function performedOn(Model $model)
    {
        $this->getActivity()->subject()->associate($model);

        return $this;
    }

    // public function setRelated($model)
    // {
    //     $this->getActivity()->{config('activity-log.related_id_column')} = $this->getRelatedId($model);
    //     $this->getActivity()->{config('activity-log.related_type_column')} = $this->getRelatedType($model);

    //     return $this;
    // }

    // protected function getRelatedId($model)
    // {
    //     $request = $this->getRequestBag();
    //     $relatedValue = $request->get(config('activity-log.related_id_column'), '');
    //     if ($relatedValue) {
    //         return $relatedValue;
    //     }

    //     if ($model->{config('activity-log.related_id_column')}) {
    //         return $model->{config('activity-log.related_id_column')};
    //     }

    //     return $model->id;
    // }

    // protected function getRelatedType($model)
    // {
    //     if (method_exists($model,'relatedType') && !empty($relatedType = $model->relatedType())) {
    //         return $relatedType;
    //     }

    //     $request = $this->getRequestBag();
    //     $relatedValue = $request->get(config('activity-log.related_type_column'), '');
    //     if ($relatedValue) {
    //         return $relatedValue;
    //     }

    //     if ($model->{config('activity-log.related_type_column')}) {
    //         return $model->{config('activity-log.related_type_column')};
    //     }

    //     return Str::singular(Str::studly($model->getTable()));
    // }

    public function causedBy($modelOrId)
    {
        if ($modelOrId === null) {
            return $this;
        }

        $model = $this->normalizeCauser($modelOrId);
        $this->getActivity()->causer()->associate($model);

        return $this;
    }

    public function causedByAnonymous()
    {
        $this->activity->causer_id = null;
        $this->activity->causer_type = null;

        return $this;
    }

    public function createdAt(Carbon $dateTime)
    {
        $this->getActivity()->created_at = $dateTime;

        return $this;
    }

    public function enableLogging()
    {
        $this->logStatus->enable();

        return $this;
    }

    public function disableLogging()
    {
        $this->logStatus->disable();

        return $this;
    }

    public function log(string $description)
    {
        if ($this->logStatus->disabled()) {
            return;
        }
        $this->getActivity();
        $this->activity->description = $description;

        $this->activity->save();

        $this->activity = null;

        return $this->activity;
    }

    protected function normalizeCauser($modelOrId): Model
    {
        if ($modelOrId instanceof Model) {
            return $modelOrId;
        }

        $guard = $this->auth->guard($this->authDriver);
        $provider = method_exists($guard, 'getProvider') ? $guard->getProvider() : null;
        $model = method_exists($provider, 'retrieveById') ? $provider->retrieveById($modelOrId) : null;

        if ($model instanceof Model) {
            return $model;
        }

        throw CouldNotLogActivity::couldNotDetermineUser($modelOrId);
    }

    protected function getActivity(): ActivityLog
    {
        if (!$this->activity instanceof ActivityLog) {
            $this->activity = app(ActivityLog::class);
            $this->causedBy($this->auth->guard($this->authDriver)->user());
        }

        return $this->activity;
    }

    protected function getRequestBag()
    {
        $_FILES = array(); //reset fileBag
        return Request::createFromGlobals();
    }
}
