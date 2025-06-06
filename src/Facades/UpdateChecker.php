<?php

namespace OginiScoutDriver\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool hasUpdate()
 * @method static string|null getLatestVersion()
 * @method static string getCurrentVersion()
 * @method static array getUpdateInfo()
 * @method static array getReleaseNotes(string $version)
 * @method static bool hasSecurityUpdate()
 * @method static void clearCache()
 */
class UpdateChecker extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \OginiScoutDriver\Services\UpdateChecker::class;
    }
}
