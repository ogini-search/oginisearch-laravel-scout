<?php

namespace OginiScoutDriver\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use ReflectionClass;
use ReflectionException;

class ModelDiscoveryService
{
    protected array $searchPaths;
    protected array $cachedModels = [];
    protected bool $cacheEnabled;

    public function __construct(?array $searchPaths = null, bool $cacheEnabled = true)
    {
        if ($searchPaths === null) {
            try {
                $this->searchPaths = [
                    app_path('Models'),
                    app_path(), // Legacy Laravel apps without Models directory
                ];
            } catch (\Exception $e) {
                // Fallback for test environment where app_path() might not be available
                $this->searchPaths = [
                    __DIR__ . '/../../tests/Models',
                    __DIR__ . '/../..',
                ];
            }
        } else {
            $this->searchPaths = $searchPaths;
        }
        $this->cacheEnabled = $cacheEnabled;
    }

    /**
     * Discover all models with the Searchable trait.
     *
     * @return array Array of model class names
     */
    public function discoverSearchableModels(): array
    {
        if ($this->cacheEnabled && !empty($this->cachedModels)) {
            return $this->cachedModels;
        }

        $models = [];

        foreach ($this->searchPaths as $path) {
            if (File::isDirectory($path)) {
                $models = array_merge($models, $this->scanDirectoryForModels($path));
            }
        }

        $searchableModels = array_filter($models, [$this, 'isSearchableModel']);

        if ($this->cacheEnabled) {
            $this->cachedModels = $searchableModels;
        }

        return $searchableModels;
    }

    /**
     * Get searchable models as a name => class array for easier CLI usage.
     *
     * @return array
     */
    public function getSearchableModelsMap(): array
    {
        $models = $this->discoverSearchableModels();
        $map = [];

        foreach ($models as $modelClass) {
            $shortName = class_basename($modelClass);
            $map[$shortName] = $modelClass;
        }

        return $map;
    }

    /**
     * Resolve a model class from various input formats.
     *
     * @param string $modelInput
     * @return string|null
     */
    public function resolveModelClass(string $modelInput): ?string
    {
        // If it's already a full class name and exists
        if (class_exists($modelInput) && $this->isSearchableModel($modelInput)) {
            return $modelInput;
        }

        // Try to find by short name
        $modelsMap = $this->getSearchableModelsMap();

        if (isset($modelsMap[$modelInput])) {
            return $modelsMap[$modelInput];
        }

        // Try App\Models\ namespace
        $appModelsClass = "App\\Models\\{$modelInput}";
        if (class_exists($appModelsClass) && $this->isSearchableModel($appModelsClass)) {
            return $appModelsClass;
        }

        // Try App\ namespace (legacy)
        $appClass = "App\\{$modelInput}";
        if (class_exists($appClass) && $this->isSearchableModel($appClass)) {
            return $appClass;
        }

        return null;
    }

    /**
     * Check if a model uses the Searchable trait.
     *
     * @param string $modelClass
     * @return bool
     */
    public function isSearchableModel(string $modelClass): bool
    {
        if (!class_exists($modelClass)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($modelClass);

            // Check if it's a model (extends Illuminate\Database\Eloquent\Model)
            if (!$reflection->isSubclassOf('Illuminate\Database\Eloquent\Model')) {
                return false;
            }

            // Check if it uses the Searchable trait
            $traits = $this->getAllTraits($reflection);

            return in_array(Searchable::class, $traits);
        } catch (ReflectionException $e) {
            return false;
        }
    }

    /**
     * Get detailed information about all searchable models.
     *
     * @return array
     */
    public function getModelDetails(): array
    {
        $models = $this->discoverSearchableModels();
        $details = [];

        foreach ($models as $modelClass) {
            try {
                $model = new $modelClass();
                $details[] = [
                    'class' => $modelClass,
                    'short_name' => class_basename($modelClass),
                    'index_name' => $model->searchableAs(),
                    'table' => $model->getTable(),
                    'searchable_fields' => $this->getSearchableFields($model),
                ];
            } catch (\Exception $e) {
                $details[] = [
                    'class' => $modelClass,
                    'short_name' => class_basename($modelClass),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $details;
    }

    /**
     * Validate that a model is properly configured for search.
     *
     * @param string $modelClass
     * @return array Validation results
     */
    public function validateModel(string $modelClass): array
    {
        $results = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'info' => [],
        ];

        if (!class_exists($modelClass)) {
            $results['errors'][] = "Class {$modelClass} does not exist";
            return $results;
        }

        if (!$this->isSearchableModel($modelClass)) {
            $results['errors'][] = "Model {$modelClass} does not use the Searchable trait";
            return $results;
        }

        try {
            $model = new $modelClass();

            // Check searchableAs method
            $indexName = $model->searchableAs();
            if (empty($indexName)) {
                $results['warnings'][] = "Model has empty index name from searchableAs()";
            } else {
                $results['info'][] = "Index name: {$indexName}";
            }

            // Check toSearchableArray method
            $searchableData = $model->toSearchableArray();
            if (empty($searchableData)) {
                $results['warnings'][] = "Model returns empty array from toSearchableArray()";
            } else {
                $results['info'][] = "Searchable fields: " . implode(', ', array_keys($searchableData));
            }

            // Check getScoutKey method
            $scoutKey = $model->getScoutKey();
            if ($scoutKey === null) {
                $results['warnings'][] = "Model getScoutKey() returns null";
            }

            $results['valid'] = true;
        } catch (\Exception $e) {
            $results['errors'][] = "Failed to instantiate model: " . $e->getMessage();
        }

        return $results;
    }

    /**
     * Clear the cached models.
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cachedModels = [];
    }

    /**
     * Add additional search paths.
     *
     * @param array $paths
     * @return void
     */
    public function addSearchPaths(array $paths): void
    {
        $this->searchPaths = array_merge($this->searchPaths, $paths);
        $this->clearCache();
    }

    /**
     * Scan a directory for PHP model files.
     *
     * @param string $directory
     * @return array
     */
    protected function scanDirectoryForModels(string $directory): array
    {
        $models = [];

        $files = File::allFiles($directory);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $className = $this->getClassNameFromFile($file->getPathname());
                if ($className) {
                    $models[] = $className;
                }
            }
        }

        return $models;
    }

    /**
     * Extract class name from PHP file.
     *
     * @param string $filePath
     * @return string|null
     */
    protected function getClassNameFromFile(string $filePath): ?string
    {
        $relativePath = str_replace(app_path(), '', $filePath);
        $relativePath = ltrim($relativePath, '/\\');
        $relativePath = str_replace(['/', '\\'], '\\', $relativePath);
        $className = str_replace('.php', '', $relativePath);

        $fullClassName = 'App\\' . $className;

        return class_exists($fullClassName) ? $fullClassName : null;
    }

    /**
     * Get all traits used by a class including parent classes.
     *
     * @param ReflectionClass $class
     * @return array
     */
    protected function getAllTraits(ReflectionClass $class): array
    {
        $traits = [];

        do {
            $traits = array_merge($traits, $class->getTraitNames());
        } while ($class = $class->getParentClass());

        return array_unique($traits);
    }

    /**
     * Get searchable fields from a model instance.
     *
     * @param mixed $model
     * @return array
     */
    protected function getSearchableFields($model): array
    {
        try {
            $searchableArray = $model->toSearchableArray();
            return array_keys($searchableArray);
        } catch (\Exception $e) {
            return [];
        }
    }
}
