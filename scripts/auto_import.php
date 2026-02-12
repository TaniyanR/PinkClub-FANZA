<?php
declare(strict_types=1);

/**
 * Auto Import Script for DMM API
 * 
 * This script should be executed via cron for automatic item imports.
 * 
 * Cron Example (run every hour):
 * 0 * * * * /usr/bin/php /path/to/PinkClub-FANZA/scripts/auto_import.php >> /path/to/logs/cron.log 2>&1
 * 
 * Cron Example (run every 3 hours):
 * 0 STAR/3 * * * /usr/bin/php /path/to/PinkClub-FANZA/scripts/auto_import.php >> /path/to/logs/cron.log 2>&1
 * (Replace STAR with asterisk character)
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/dmm_api.php';
require_once __DIR__ . '/../lib/repository.php';

/**
 * Check and acquire lock for auto import
 */
function acquire_auto_import_lock(PDO $pdo): bool
{
    try {
        // Clean up expired locks
        $stmt = $pdo->prepare(
            'UPDATE api_schedules 
             SET lock_until = NULL 
             WHERE schedule_type = :type 
             AND lock_until IS NOT NULL 
             AND lock_until < NOW()'
        );
        $stmt->execute([':type' => 'auto_import']);

        // Try to acquire lock
        $stmt = $pdo->prepare(
            'UPDATE api_schedules 
             SET lock_until = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
                 updated_at = NOW()
             WHERE schedule_type = :type 
             AND is_enabled = 1
             AND (lock_until IS NULL OR lock_until < NOW())'
        );
        $stmt->execute([':type' => 'auto_import']);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('acquire_auto_import_lock error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Release lock for auto import
 */
function release_auto_import_lock(PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare(
            'UPDATE api_schedules 
             SET lock_until = NULL,
                 updated_at = NOW()
             WHERE schedule_type = :type'
        );
        $stmt->execute([':type' => 'auto_import']);
    } catch (PDOException $e) {
        error_log('release_auto_import_lock error: ' . $e->getMessage());
    }
}

/**
 * Update last run timestamp
 */
function update_last_run(PDO $pdo): void
{
    try {
        $stmt = $pdo->prepare(
            'UPDATE api_schedules 
             SET last_run_at = NOW(),
                 next_run_at = DATE_ADD(NOW(), INTERVAL interval_minutes MINUTE),
                 updated_at = NOW()
             WHERE schedule_type = :type'
        );
        $stmt->execute([':type' => 'auto_import']);
    } catch (PDOException $e) {
        error_log('update_last_run error: ' . $e->getMessage());
    }
}

/**
 * Log API result
 */
function log_api_result(PDO $pdo, string $endpoint, array $params, array $result): void
{
    try {
        $isSuccess = $result['ok'] ?? false;
        $itemCount = 0;
        
        if ($isSuccess && isset($result['data']['result']['items'])) {
            $items = $result['data']['result']['items'];
            $itemCount = is_array($items) ? count($items) : 0;
        }
        
        $stmt = $pdo->prepare(
            'INSERT INTO api_logs (
                created_at, endpoint, params_json, status, http_code, 
                item_count, error_message, success
             ) VALUES (
                NOW(), :endpoint, :params_json, :status, :http_code,
                :item_count, :error_message, :success
             )'
        );
        
        $stmt->execute([
            ':endpoint' => $endpoint,
            ':params_json' => json_encode($params, JSON_UNESCAPED_UNICODE),
            ':status' => $isSuccess ? 'success' : 'error',
            ':http_code' => $result['http_code'] ?? 0,
            ':item_count' => $itemCount,
            ':error_message' => $result['error'] ?? null,
            ':success' => $isSuccess ? 1 : 0,
        ]);
    } catch (PDOException $e) {
        error_log('log_api_result error: ' . $e->getMessage());
    }
}

/**
 * Get API configuration
 */
function get_api_config(): array
{
    return [
        'api_id' => (string)(config_get('dmm_api.api_id') ?? ''),
        'affiliate_id' => (string)(config_get('dmm_api.affiliate_id') ?? ''),
        'site' => (string)(config_get('dmm_api.site') ?? 'FANZA'),
        'service' => (string)(config_get('dmm_api.service') ?? 'digital'),
        'floor' => (string)(config_get('dmm_api.floor') ?? 'videoa'),
    ];
}

/**
 * Main execution
 */
function main(): int
{
    echo "[" . date('Y-m-d H:i:s') . "] Auto import started\n";
    
    try {
        $pdo = db();
        
        // Check if api_schedules table exists
        $tables = $pdo->query("SHOW TABLES LIKE 'api_schedules'")->fetchAll();
        if (empty($tables)) {
            echo "Error: api_schedules table does not exist. Run migration first.\n";
            return 1;
        }
        
        // Try to acquire lock
        if (!acquire_auto_import_lock($pdo)) {
            echo "Another import is already running or schedule is disabled. Exiting.\n";
            return 0;
        }
        
        echo "Lock acquired successfully\n";
        
        // Get API configuration
        $apiConfig = get_api_config();
        
        if (empty($apiConfig['api_id']) || empty($apiConfig['affiliate_id'])) {
            echo "Error: API configuration is incomplete\n";
            release_auto_import_lock($pdo);
            return 1;
        }
        
        echo "API config loaded: site={$apiConfig['site']}, service={$apiConfig['service']}, floor={$apiConfig['floor']}\n";
        
        // Make API request
        $endpoint = 'ItemList';
        $params = array_merge($apiConfig, [
            'hits' => 100,
            'offset' => 1,
            'sort' => 'date',
        ]);
        
        echo "Calling DMM API...\n";
        $result = dmm_api_request($endpoint, $params);
        
        // Log the result
        log_api_result($pdo, $endpoint, $params, $result);
        
        if (!$result['ok']) {
            echo "API request failed: " . ($result['error'] ?? 'unknown error') . "\n";
            release_auto_import_lock($pdo);
            return 1;
        }
        
        $items = $result['data']['result']['items'] ?? [];
        if (!is_array($items)) {
            $items = [];
        }
        
        echo "Retrieved " . count($items) . " items\n";
        
        // Update last run timestamp
        update_last_run($pdo);
        
        // Release lock
        release_auto_import_lock($pdo);
        
        echo "[" . date('Y-m-d H:i:s') . "] Auto import completed successfully\n";
        return 0;
        
    } catch (Throwable $e) {
        echo "Fatal error: " . $e->getMessage() . "\n";
        error_log('auto_import fatal error: ' . $e->getMessage());
        
        try {
            release_auto_import_lock(db());
        } catch (Throwable $e2) {
            error_log('Failed to release lock: ' . $e2->getMessage());
        }
        
        return 1;
    }
}

// Run only when executed directly (not included)
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(main());
}
