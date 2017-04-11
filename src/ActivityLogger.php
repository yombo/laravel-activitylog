<?php

namespace Spatie\Activitylog;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Macroable;
use Spatie\Activitylog\Exceptions\CouldNotLogActivity;
use Spatie\Activitylog\Models\Activity;

class ActivityLogger
{
    use Macroable;

    /** @var \Illuminate\Auth\AuthManager */
    protected $auth;

    /** @var \Illuminate\Contracts\Config\Repository  */
    protected $config;

    protected $logName = '';

    protected $requestId = '';

    protected $ipAddress = '';

    protected $severity = '';

    protected $sourceType = '';

    protected $sourceName = '';

    protected $subjectId = null;

    protected $subjectType = null;

    protected $causerId = null;

    protected $causerType = null;

    /** @var bool */
    protected $logEnabled;

    /** @var \Illuminate\Database\Eloquent\Model */
    protected $performedOn;

    /** @var \Illuminate\Database\Eloquent\Model */
    protected $causedBy;

    /** @var \Illuminate\Support\Collection */
    protected $properties;

    public function __construct(AuthManager $auth, Repository $config)
    {
        $this->auth = $auth;

        $this->config = $config;

        $this->properties = collect();

        $authDriver = $config['laravel-activitylog']['default_auth_driver'] ?? $auth->getDefaultDriver();

        $this->causedBy = $auth->guard($authDriver)->user();

        $this->logName = $config['laravel-activitylog']['default_log_name'];

        $this->requestId = $config['laravel-activitylog']['default_request_id'];

        $this->severity = $config['laravel-activitylog']['default_severity'];

        $this->sourceType = $config['laravel-activitylog']['default_source_type'];

        $this->sourceName = $config['laravel-activitylog']['default_source_name'];

        $this->logEnabled = $config['laravel-activitylog']['enabled'] ?? true;
        
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
            $ip_info = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ipAddress = array_pop($ip_info);
        }
        $this->ipAddress = $ipAddress;
    }

    public function performedOn(Model $model)
    {
        $this->performedOn = $model;

        return $this;
    }

    public function on(Model $model)
    {
        return $this->performedOn($model);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model|int|string $modelOrId
     *
     * @return $this
     */
    public function causedBy($modelOrId)
    {
        $model = $this->normalizeCauser($modelOrId);

        $this->causedBy = $model;

        return $this;
    }

    public function by($modelOrId)
    {
        return $this->causedBy($modelOrId);
    }

    /**
     * @param array|\Illuminate\Support\Collection $properties
     *
     * @return $this
     */
    public function withProperties($properties)
    {
        $this->properties = collect($properties);

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function withProperty(string $key, $value)
    {
        $this->properties->put($key, $value);

        return $this;
    }

    public function requestId(string $requestId)
    {
        $this->requestId = $requestId;

        return $this;
    }

    public function ipAddress(string $ipAddress)
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function severity(string $severity)
    {
        $this->severity = $severity;

        return $this;
    }

    public function source(string $sourceType, string $sourceName)
    {
        $this->sourceType = $sourceType;
        $this->sourceName = $sourceName;

        return $this;
    }

    public function subjectId($subjectId)
    {
        $this->subjectId = $subjectId;

        return $this;
    }

    public function subjectType($subjectType)
    {
        $this->subjectType = $subjectType;

        return $this;
    }

    public function causerId($causerId)
    {
        $this->causerId = $causerId;

        return $this;
    }

    public function causerType($causerType)
    {
        $this->causerType = $causerType;

        return $this;
    }

    public function useLog(string $logName)
    {
        $this->logName = $logName;

        return $this;
    }

    public function inLog(string $logName)
    {
        return $this->useLog($logName);
    }

    /**
     * @param string $description
     *
     * @return null|mixed
     */
    public function log(string $description)
    {
        if (! $this->logEnabled) {
            return;
        }

        $activity = ActivitylogServiceProvider::getActivityModelInstance();

        if ($this->performedOn) {
            $activity->subject()->associate($this->performedOn);
        }

        if ($this->causedBy) {
            $activity->causer()->associate($this->causedBy);
        }

        $activity->properties = $this->properties;

        $activity->description = $this->replacePlaceholders($description, $activity);

        $activity->log_name = $this->logName;

        $activity->request_id = $this->requestId;

        $activity->ip_address = $this->ipAddress;

        $activity->severity = $this->severity;

        $activity->source_type = $this->sourceType;
        $activity->source_name = $this->sourceName;

        if ($this->subjectId) $activity->subject_id = $this->subjectId;
        if ($this->subjectType) $activity->subject_type = $this->subjectType;
        if ($this->subjectId) $activity->subject_id = $this->subjectId;
        if ($this->causerType) $activity->causer_type = $this->causerType;

        $activity->save();

        return $activity;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model|int|string $modelOrId
     *
     * @throws \Spatie\Activitylog\Exceptions\CouldNotLogActivity
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function normalizeCauser($modelOrId): Model
    {
        if ($modelOrId instanceof Model) {
            return $modelOrId;
        }

        if($this->config['laravel-activitylog']['use_jwt_token']) {
            if($model = JWTAuth::parseToken()->authenticate()) {
                return $model;
            }
        } else {
            if ($model = $this->auth->getProvider()->retrieveById($modelOrId)) {
                return $model;
            }
        }

        throw CouldNotLogActivity::couldNotDetermineUser($modelOrId);
    }

    protected function replacePlaceholders(string $description, Activity $activity): string
    {
        return preg_replace_callback('/:[a-z0-9._-]+/i', function ($match) use ($activity) {
            $match = $match[0];

            $attribute = (string) string($match)->between(':', '.');

            if (! in_array($attribute, ['subject', 'causer', 'properties'])) {
                return $match;
            }

            $propertyName = substr($match, strpos($match, '.') + 1);

            $attributeValue = $activity->$attribute;

            $attributeValue = $attributeValue->toArray();

            return array_get($attributeValue, $propertyName, $match);
        }, $description);
    }
}
