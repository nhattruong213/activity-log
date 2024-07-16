<?php

namespace NNT\ActivityLog\Models;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use DateTimeInterface;


class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = ['log_type', 'description', 'causer_id', 'causer_type', 'subject_id', 'subject_type', 'before_value', 'after_value'];

    public function __construct(array $attributes = [])
    {
        if (!isset($this->table)) {
            $this->setTable(config('activity-log.table_name'));
        }

        parent::__construct($attributes);
    }

    public function subject(): MorphTo
    {
        if (config('activity-log.subject_returns_soft_deleted_models')) {
            return $this->morphTo()->withTrashed();
        }

        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the car's owner.
     */
    public function employee()
    {
        return $this->hasOneThrough(
            Employee::class,
            User::class,
            'id', 
            'id',
            'causer_id',
            'employee_id',
        );
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $this->localize('created_at')->format('Y-m-d H:i:s');
    }
}
