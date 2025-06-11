<?php

namespace OginiScoutDriver\Console\Commands;

use Illuminate\Console\Command;
use OginiScoutDriver\Jobs\BulkScoutImportJob;
use OginiScoutDriver\Services\ModelDiscoveryService;
use OginiScoutDriver\Engine\OginiEngine;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\App;
use Exception;

class BulkImportCommand extends Command
{
    protected $signature = 'ogini:bulk-import 
                           {model? : Model class to import (optional - shows available models if not provided)}
                           {--list : List all available searchable models}
                           {--limit=0 : Maximum records to import (0 = all)}
                           {--batch-size=500 : Documents per bulk API call}
                           {--chunk-size=1000 : Records per database query}
                           {--queue : Process via queue instead of immediate}
                           {--force : Flush existing index first}
                           {--dry-run : Test without actual indexing}
                           {--validate : Validate model configuration}';

    protected $description = 'Efficiently import models to OginiSearch using bulk operations';

    protected ModelDiscoveryService $modelDiscovery;

    public function handle()
    {
        $this->modelDiscovery = app(ModelDiscoveryService::class);

        // Handle --list option
        if ($this->option('list')) {
            return $this->listAvailableModels();
        }

        $modelInput = $this->argument('model');

        // If no model provided, show available models
        if (!$modelInput) {
            return $this->showAvailableModels();
        }

        // Resolve the model class
        $modelClass = $this->modelDiscovery->resolveModelClass($modelInput);

        if (!$modelClass) {
            $this->error("❌ Model '{$modelInput}' not found or not searchable");
            $this->line("Use --list to see available searchable models");
            return 1;
        }

        // Handle --validate option
        if ($this->option('validate')) {
            return $this->validateModel($modelClass);
        }

        // Test connection
        if (!$this->testConnection()) {
            return 1;
        }

        $modelName = class_basename($modelClass);
        $this->info("🚀 Starting bulk import for {$modelName} ({$modelClass})");

        // Handle force flush
        if ($this->option('force')) {
            $this->flushModel($modelClass);
        }

        // Process based on queue option
        if ($this->option('queue')) {
            return $this->processWithQueue($modelClass, $modelName);
        } else {
            return $this->processImmediately($modelClass, $modelName);
        }
    }

    private function testConnection(): bool
    {
        try {
            $config = config('oginisearch');
            $baseUrl = $config['base_url'] ?? 'http://localhost:3000';
            $response = Http::timeout(5)->get("{$baseUrl}/health");

            if ($response->successful()) {
                $this->info("✅ OginiSearch server is accessible");
                return true;
            }
        } catch (Exception $e) {
            $this->error("❌ Cannot connect to OginiSearch: " . $e->getMessage());
            return false;
        }

        return false;
    }

    private function processImmediately(string $modelClass, string $modelName): int
    {
        // Handle execution timeout for large datasets
        $this->handleExecutionTimeout();

        $totalCount = $modelClass::count();
        $limit = (int) $this->option('limit');
        $recordsToProcess = $limit > 0 ? min($limit, $totalCount) : $totalCount;

        $this->info("📊 Processing {$recordsToProcess} records");

        // Warn about large datasets and suggest queue option
        if ($recordsToProcess > 10000 && !$this->option('queue')) {
            $this->warn("⚠️  Processing {$recordsToProcess} records without queue. Consider using --queue for large datasets.");
            if (!$this->confirm('Continue with immediate processing?')) {
                $this->info('💡 Use --queue option for better handling of large datasets.');
                return 0;
            }
        }

        $progressBar = $this->output->createProgressBar($recordsToProcess);
        $progressBar->start();

        $processed = 0;
        $successCount = 0;
        $errorCount = 0;
        $chunkSize = (int) $this->option('chunk-size');
        $startTime = microtime(true);

        // Get the OginiEngine for direct progress tracking
        $engine = null;
        if (!$this->option('dry-run')) {
            try {
                $engine = App::make(OginiEngine::class);
            } catch (Exception $e) {
                $this->warn("Could not get OginiEngine directly, falling back to Scout methods");
            }
        }

        $modelClass::query()
            ->when($limit > 0, function ($query) use ($limit) {
                return $query->limit($limit);
            })
            ->chunk($chunkSize, function ($models) use (
                &$processed,
                &$successCount,
                &$errorCount,
                &$progressBar,
                $recordsToProcess,
                $engine
            ) {
                if ($processed >= $recordsToProcess) {
                    return false;
                }

                $batchModels = $models->take($recordsToProcess - $processed);

                if (!$this->option('dry-run')) {
                    try {
                        if ($engine && $engine->getBatchProcessor()) {
                            // Use OginiEngine directly with progress tracking
                            $progressCallback = function ($processedInBatch, $batchSize, $batchIndex, $totalBatches) use (&$progressBar) {
                                $progressBar->advance($batchSize);
                            };

                            $engine->update($batchModels, $progressCallback);
                            $successCount += $batchModels->count();
                        } else {
                            // Fallback to Scout's bulk processing
                            $batchModels->searchable();
                            $successCount += $batchModels->count();
                            $progressBar->advance($batchModels->count());
                        }
                    } catch (Exception $e) {
                        $this->newLine();
                        $this->error("   ❌ Error processing batch: " . $e->getMessage());
                        $errorCount += $batchModels->count();
                        $progressBar->advance($batchModels->count());
                    }
                } else {
                    $successCount += $batchModels->count();
                    $progressBar->advance($batchModels->count());
                }

                $processed += $batchModels->count();

                if ($processed >= $recordsToProcess) {
                    return false;
                }
            });

        $progressBar->finish();
        $this->newLine();

        $duration = round(microtime(true) - $startTime, 2);
        $throughput = $processed > 0 ? round($processed / $duration, 2) : 0;

        $this->info("✅ Import completed!");
        $this->info("📈 Results:");
        $this->info("   - Total processed: {$processed}");
        $this->info("   - Successful: {$successCount}");
        $this->info("   - Errors: {$errorCount}");
        $this->info("   - Duration: {$duration} seconds");
        $this->info("   - Throughput: {$throughput} docs/second");

        return $errorCount > 0 ? 1 : 0;
    }

    /**
     * Handle execution timeout configuration for long-running imports.
     *
     * @return void
     */
    private function handleExecutionTimeout(): void
    {
        // Set unlimited execution time for Artisan commands (like Laravel Scout does)
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        // Increase memory limit if needed for large datasets
        $currentMemoryLimit = ini_get('memory_limit');
        $recommendedMemoryLimit = '1G';

        if ($this->convertToBytes($currentMemoryLimit) < $this->convertToBytes($recommendedMemoryLimit)) {
            if (function_exists('ini_set')) {
                @ini_set('memory_limit', $recommendedMemoryLimit);
                $this->line("💾 Memory limit increased to {$recommendedMemoryLimit} for large dataset processing");
            }
        }

        // Configure extended timeouts for the OginiEngine if available
        $this->configureExtendedTimeouts();
    }

    /**
     * Configure extended timeouts for bulk operations.
     *
     * @return void
     */
    private function configureExtendedTimeouts(): void
    {
        try {
            // Set extended timeouts for bulk operations
            $extendedConfig = [
                'ogini.client.timeout' => 300,                    // 5 minutes per request
                'ogini.performance.batch_processing.timeout' => 600, // 10 minutes per batch
                'ogini.performance.connection_pool.request_timeout' => 300,
            ];

            foreach ($extendedConfig as $key => $value) {
                config([$key => $value]);
            }

            $this->line("⏱️  Extended timeouts configured for bulk processing");
        } catch (\Exception $e) {
            // Ignore configuration errors in test environments
        }
    }

    /**
     * Convert memory limit string to bytes.
     *
     * @param string $memoryLimit
     * @return int
     */
    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $number = (int) $memoryLimit;

        switch ($last) {
            case 'g':
                $number *= 1024;
                // no break
            case 'm':
                $number *= 1024;
                // no break
            case 'k':
                $number *= 1024;
        }

        return $number;
    }

    private function processWithQueue(string $modelClass, string $modelName): int
    {
        $this->info("🔄 Queueing bulk import jobs...");

        $totalCount = $modelClass::count();
        $limit = (int) $this->option('limit');
        $chunkSize = (int) $this->option('chunk-size');

        $jobsDispatched = 0;

        $modelClass::query()
            ->when($limit > 0, function ($query) use ($limit) {
                return $query->limit($limit);
            })
            ->chunk($chunkSize, function ($models) use (&$jobsDispatched, $modelClass) {
                BulkScoutImportJob::dispatch(
                    $models->pluck('id')->toArray(),
                    $modelClass,
                    (int) $this->option('batch-size')
                );
                $jobsDispatched++;
            });

        $this->info("✅ Dispatched {$jobsDispatched} bulk import jobs to queue");
        $this->line("   Run: php artisan queue:work --timeout=600 to process them");

        return 0;
    }

    private function flushModel(string $modelClass): void
    {
        $this->warn("🗑️  Flushing existing index...");

        if (!$this->option('dry-run')) {
            $modelClass::removeAllFromSearch();
        }

        $this->info("✅ Index flushed");
    }

    private function listAvailableModels(): int
    {
        $details = $this->modelDiscovery->getModelDetails();

        if (empty($details)) {
            $this->warn("⚠️  No searchable models found in your application");
            $this->line("Make sure your models use the Laravel\\Scout\\Searchable trait");
            return 1;
        }

        $this->info("📋 Available Searchable Models:");
        $this->newLine();

        $headers = ['Short Name', 'Full Class', 'Index Name', 'Table', 'Searchable Fields'];
        $rows = [];

        foreach ($details as $detail) {
            if (isset($detail['error'])) {
                $rows[] = [
                    $detail['short_name'],
                    $detail['class'],
                    '<error>',
                    '<error>',
                    'Error: ' . $detail['error']
                ];
            } else {
                $rows[] = [
                    $detail['short_name'],
                    $detail['class'],
                    $detail['index_name'],
                    $detail['table'],
                    implode(', ', array_slice($detail['searchable_fields'], 0, 3)) .
                        (count($detail['searchable_fields']) > 3 ? '...' : '')
                ];
            }
        }

        $this->table($headers, $rows);
        $this->newLine();
        $this->line("💡 Usage: php artisan ogini:bulk-import <Short Name>");
        $this->line("   Example: php artisan ogini:bulk-import User");

        return 0;
    }

    private function showAvailableModels(): int
    {
        $modelsMap = $this->modelDiscovery->getSearchableModelsMap();

        if (empty($modelsMap)) {
            $this->warn("⚠️  No searchable models found in your application");
            $this->line("Make sure your models use the Laravel\\Scout\\Searchable trait");
            return 1;
        }

        $this->info("📋 Available Searchable Models:");
        foreach ($modelsMap as $shortName => $fullClass) {
            $this->line("  • {$shortName} ({$fullClass})");
        }

        $this->newLine();
        $this->line("💡 Usage: php artisan ogini:bulk-import <model>");
        $this->line("   Example: php artisan ogini:bulk-import User");
        $this->line("   Use --list for detailed information");

        return 0;
    }

    private function validateModel(string $modelClass): int
    {
        $validation = $this->modelDiscovery->validateModel($modelClass);
        $modelName = class_basename($modelClass);

        $this->info("🔍 Validating model: {$modelName}");
        $this->line("Class: {$modelClass}");
        $this->newLine();

        if (!empty($validation['errors'])) {
            $this->error("❌ Validation failed:");
            foreach ($validation['errors'] as $error) {
                $this->line("  • {$error}");
            }
            return 1;
        }

        if ($validation['valid']) {
            $this->info("✅ Model is valid for bulk import");
        }

        if (!empty($validation['warnings'])) {
            $this->warn("⚠️  Warnings:");
            foreach ($validation['warnings'] as $warning) {
                $this->line("  • {$warning}");
            }
        }

        if (!empty($validation['info'])) {
            $this->info("ℹ️  Information:");
            foreach ($validation['info'] as $info) {
                $this->line("  • {$info}");
            }
        }

        // Test record count
        try {
            $count = $modelClass::count();
            $this->line("📊 Total records: {$count}");
        } catch (\Exception $e) {
            $this->warn("⚠️  Could not get record count: " . $e->getMessage());
        }

        return 0;
    }
}
