<?php

namespace OginiScoutDriver\Console;

use Illuminate\Console\Command;
use OginiScoutDriver\Services\UpdateChecker;

class CheckUpdatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ogini:check-updates
                            {--clear-cache : Clear the update cache before checking}
                            {--security-only : Only show security updates}
                            {--json : Output in JSON format}';

    /**
     * The console command description.
     */
    protected $description = 'Check for available updates to the Ogini Laravel Scout driver';

    /**
     * Execute the console command.
     */
    public function handle(UpdateChecker $updateChecker): int
    {
        $this->line('');
        $this->info('🔍 Checking for Ogini Laravel Scout Driver updates...');
        $this->line('');

        // Clear cache if requested
        if ($this->option('clear-cache')) {
            $updateChecker->clearCache();
            $this->comment('Cache cleared.');
        }

        // Get update information
        $updateInfo = $updateChecker->getUpdateInfo();

        // Handle JSON output
        if ($this->option('json')) {
            $this->line(json_encode($updateInfo, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        // Display current version
        $this->table(['Package', 'Current Version'], [
            ['ogini-search/laravel-scout-driver', $updateInfo['current_version']],
        ]);

        // Check if there are updates
        if (!$updateInfo['has_update']) {
            $this->success('✅ You are running the latest version!');
            return Command::SUCCESS;
        }

        // Filter security-only updates if requested
        if ($this->option('security-only') && !$updateInfo['security_update']) {
            $this->info('No security updates available.');
            return Command::SUCCESS;
        }

        $this->displayUpdateNotification($updateInfo);

        return Command::SUCCESS;
    }

    /**
     * Display update notification with details
     */
    protected function displayUpdateNotification(array $updateInfo): void
    {
        $this->line('');
        $this->warn('🚀 Update Available!');
        $this->line('');

        // Update summary table
        $this->table(['', 'Version'], [
            ['Current', $updateInfo['current_version']],
            ['Latest', $updateInfo['latest_version']],
        ]);

        // Security warning
        if ($updateInfo['security_update']) {
            $this->error('🚨 This update contains security fixes!');
            $this->error('   Please update as soon as possible.');
            $this->line('');
        }

        // Breaking changes warning
        if ($updateInfo['breaking_changes']) {
            $this->warn('⚠️  This is a major version update and may contain breaking changes.');
            $this->warn('   Please review the changelog before updating.');
            $this->line('');
        }

        // Release notes
        if (!empty($updateInfo['release_notes']['body'])) {
            $this->info('📝 Release Notes:');
            $this->line('');
            $this->line($this->formatReleaseNotes($updateInfo['release_notes']['body']));
            $this->line('');
        }

        // Update instructions
        $this->info('📦 To update, run:');
        $this->line('   ' . $updateInfo['update_command']);

        if ($updateInfo['breaking_changes']) {
            $this->line('   composer update ogini-search/laravel-scout-driver --with-dependencies');
        }

        $this->line('');

        // Additional links
        if (!empty($updateInfo['release_notes']['html_url'])) {
            $this->comment('🔗 Full release notes: ' . $updateInfo['release_notes']['html_url']);
        }

        $this->line('');
    }

    /**
     * Format release notes for console display
     */
    protected function formatReleaseNotes(string $body): string
    {
        // Basic markdown formatting for console
        $formatted = $body;

        // Convert markdown headers to colored text
        $formatted = preg_replace('/^### (.+)$/m', '<fg=yellow>$1</>', $formatted);
        $formatted = preg_replace('/^## (.+)$/m', '<fg=green;options=bold>$1</>', $formatted);
        $formatted = preg_replace('/^# (.+)$/m', '<fg=blue;options=bold>$1</>', $formatted);

        // Convert markdown lists
        $formatted = preg_replace('/^- (.+)$/m', '  • $1', $formatted);

        // Convert bold text
        $formatted = preg_replace('/\*\*(.+?)\*\*/', '<options=bold>$1</>', $formatted);

        // Convert code blocks
        $formatted = preg_replace('/`([^`]+)`/', '<fg=cyan>$1</>', $formatted);

        // Limit length for console display
        $lines = explode("\n", $formatted);
        if (count($lines) > 20) {
            $lines = array_slice($lines, 0, 20);
            $lines[] = '...';
            $lines[] = '<comment>(truncated - see full release notes online)</comment>';
        }

        return implode("\n", $lines);
    }

    /**
     * Display success message with styling
     */
    protected function success(string $message): void
    {
        $this->line('');
        $this->info($message);
        $this->line('');
    }
}
