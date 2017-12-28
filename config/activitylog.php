<?php

return [

    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),

    /*
     * When the clean-command is executed, all recording activities older than
     * the number of days specified here will be deleted.
     */
    'delete_records_older_than_days' => 365,

    /*
     * If no log name is passed to the activity() helper
     * we use this default log name.
     */
    'default_log_name' => 'default',

    /*
     * Used to identify a single request through multiple logs.
     */
    'default_request_id' => null,

    /*
     * Severity of the activity log. One of:
     * debug, info, notice, warning, error, critical, alert, emergency
     * https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
     */
    'default_severity' => 'info',

    /*
     * Source type is usually one of: controller, model, middleware, etc.
     */
    'default_source_type' => null,

    /*
     * Name of the above file. Eg: AuthController
     */
    'default_source_name' => null,

    /*
     * You can specify an auth driver here that gets user models.
     * If this is null we'll use the default Laravel auth driver.
     */
    'default_auth_driver' => null,

    /*
     * Will use the tymondesigns/jwt-auth
     * token to extract user model.
     * NOTE: requires JWTAuth facade within the aliases section of app.php
     */
    'use_jwt_token' => false,

    /*
     * If set to true, the subject returns soft deleted models.
     */
     'subject_returns_soft_deleted_models' => false,

    /*
     * This model will be used to log activity. The only requirement is that
     * it should be or extend the Spatie\Activitylog\Models\Activity model.
     */
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,
];
