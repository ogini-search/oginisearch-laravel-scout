<?php

namespace OginiScoutDriver\Console;

use Illuminate\Console\Command;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Engine\OginiEngine;

class OginiHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ogini:health-check 
                            {--detailed : Perform detailed health checks}
                            {--json : Output results as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the health of the OginiSearch connection and API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $detailed = $this->option('detailed');
        $json = $this->option('json');

        try {
            // Get the Ogini client from Laravel's service container
            $engine = app(OginiEngine::class);
            $client = $engine->getClient();

            $this->info('🔍 Checking OginiSearch health...');
            $this->newLine();

            // Perform health check
            $healthData = $client->healthCheck($detailed);

            if ($json) {
                $this->line(json_encode($healthData, JSON_PRETTY_PRINT));
                return $this->getExitCode($healthData);
            }

            // Display formatted results
            $this->displayHealthResults($healthData);

            return $this->getExitCode($healthData);
        } catch (\Exception $e) {
            $this->error("❌ Failed to perform health check: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Display formatted health check results.
     *
     * @param array $healthData
     * @return void
     */
    protected function displayHealthResults(array $healthData): void
    {
        // Status indicator
        $statusIcon = $this->getStatusIcon($healthData['status']);
        $this->line("Status: {$statusIcon} " . strtoupper($healthData['status']));

        // Basic connectivity
        $accessIcon = $healthData['api_accessible'] ? '✅' : '❌';
        $this->line("API Accessible: {$accessIcon} " . ($healthData['api_accessible'] ? 'Yes' : 'No'));

        // Authentication
        $authIcon = $healthData['authenticated'] ? '✅' : '❌';
        $this->line("Authenticated: {$authIcon} " . ($healthData['authenticated'] ? 'Yes' : 'No'));

        // Response time
        if ($healthData['response_time_ms'] !== null) {
            $responseTime = $healthData['response_time_ms'];
            $timeIcon = $responseTime < 100 ? '🚀' : ($responseTime < 500 ? '⚡' : '🐌');
            $this->line("Response Time: {$timeIcon} {$responseTime}ms");
        }

        // Version
        if ($healthData['version']) {
            $this->line("API Version: 📦 " . $healthData['version']);
        }

        // Timestamp
        $this->line("Checked At: 🕐 " . $healthData['timestamp']);

        $this->newLine();

        // Error details
        if (isset($healthData['details']['error'])) {
            $this->error("Error Details:");
            $this->line("  " . $healthData['details']['error']);
            if (isset($healthData['details']['message'])) {
                $this->line("  " . $healthData['details']['message']);
            }
            $this->newLine();
        }

        // Detailed results
        if (isset($healthData['details']) && !isset($healthData['details']['error'])) {
            $this->displayDetailedResults($healthData['details']);
        }
    }

    /**
     * Display detailed health check results.
     *
     * @param array $details
     * @return void
     */
    protected function displayDetailedResults(array $details): void
    {
        $this->info("📊 Detailed Health Check Results:");
        $this->newLine();

        // Index listing
        if (isset($details['index_listing'])) {
            $indexData = $details['index_listing'];
            $indexIcon = $indexData['accessible'] ? '✅' : '❌';
            $this->line("Index Listing: {$indexIcon}");

            if ($indexData['accessible']) {
                $this->line("  📋 Found {$indexData['index_count']} indices");
                $this->line("  ⚡ Response time: {$indexData['response_time_ms']}ms");
            } else {
                $this->line("  ❌ Error: " . ($indexData['error'] ?? 'Unknown error'));
            }
            $this->newLine();
        }

        // Search functionality
        if (isset($details['search_functionality'])) {
            $searchData = $details['search_functionality'];
            $searchIcon = $searchData['accessible'] ? '✅' : '❌';
            $this->line("Search Functionality: {$searchIcon}");

            if ($searchData['accessible']) {
                $this->line("  🔍 Search is working properly");
                $this->line("  ⚡ Response time: {$searchData['response_time_ms']}ms");
            } else {
                $this->line("  ❌ Error: " . ($searchData['error'] ?? 'Unknown error'));
            }
            $this->newLine();
        }

        // Configuration
        if (isset($details['configuration'])) {
            $config = $details['configuration'];
            $this->line("⚙️  Configuration:");
            $this->line("  🌐 Base URL: " . $config['base_url']);
            $this->line("  ⏱️  Timeout: " . $config['timeout'] . "s");
            $this->line("  🔄 Retry Attempts: " . $config['retry_attempts']);
            $this->newLine();
        }

        // Server info
        if (isset($details['server'])) {
            $this->line("🖥️  Server Information:");
            foreach ($details['server'] as $key => $value) {
                $this->line("  " . ucfirst(str_replace('_', ' ', $key)) . ": " . $value);
            }
            $this->newLine();
        }
    }

    /**
     * Get status icon for display.
     *
     * @param string $status
     * @return string
     */
    protected function getStatusIcon(string $status): string
    {
        return match ($status) {
            'healthy' => '✅',
            'unreachable' => '🔴',
            'authentication_failed' => '🔐',
            'server_error' => '🖥️',
            'client_error' => '⚠️',
            default => '❓'
        };
    }

    /**
     * Get appropriate exit code based on health status.
     *
     * @param array $healthData
     * @return int
     */
    protected function getExitCode(array $healthData): int
    {
        return match ($healthData['status']) {
            'healthy' => 0,
            'unreachable', 'authentication_failed', 'server_error' => 1,
            default => 2
        };
    }
}
