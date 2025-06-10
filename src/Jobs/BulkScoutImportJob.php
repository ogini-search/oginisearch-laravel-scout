<?php

namespace OginiScoutDriver\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;
use Throwable;

class BulkScoutImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $modelIds;
    private $modelClass;
    private $batchSize;

    public $timeout = 600;
    public $tries = 3;

    public function __construct(array $modelIds, string $modelClass, int $batchSize = 500)
    {
        $this->modelIds = $modelIds;
        $this->modelClass = $modelClass;
        $this->batchSize = $batchSize;
    }

    public function handle()
    {
        if (!class_exists($this->modelClass)) {
            Log::error("BulkScoutImportJob: Model class {$this->modelClass} does not exist");
            return;
        }

        try {
            $models = $this->modelClass::whereIn('id', $this->modelIds)->get();

            if ($models->isEmpty()) {
                Log::warning("BulkScoutImportJob: No models found for IDs", [
                    'model' => $this->modelClass,
                    'ids_count' => count($this->modelIds)
                ]);
                return;
            }

            $modelName = class_basename($this->modelClass);
            Log::info("BulkScoutImportJob: Processing {$models->count()} {$modelName} records");

            // Process in bulk batches using Scout
            $batches = $models->chunk($this->batchSize);

            foreach ($batches as $batch) {
                try {
                    $batch->searchable();

                    // Small delay between batches
                    if ($batches->count() > 1) {
                        usleep(100000); // 100ms delay
                    }
                } catch (Exception $e) {
                    Log::error("BulkScoutImportJob: Error in batch processing", [
                        'batch_size' => $batch->count(),
                        'error' => $e->getMessage()
                    ]);

                    throw $e;
                }
            }

            Log::info("BulkScoutImportJob: Successfully processed {$models->count()} records");
        } catch (Exception $e) {
            Log::error("BulkScoutImportJob: Error processing batch", [
                'model' => $this->modelClass,
                'ids_count' => count($this->modelIds),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception)
    {
        $modelName = class_basename($this->modelClass);
        Log::error("BulkScoutImportJob: Job failed permanently", [
            'model' => $this->modelClass,
            'model_name' => $modelName,
            'ids_count' => count($this->modelIds),
            'batch_size' => $this->batchSize,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);
    }

    public function backoff()
    {
        return [60, 120, 300]; // 1 min, 2 min, 5 min
    }

    public function tags()
    {
        $modelName = class_basename($this->modelClass);
        return [
            'ogini-bulk-import',
            "model:{$modelName}",
            "batch-size:{$this->batchSize}",
            "records:" . count($this->modelIds)
        ];
    }
}
