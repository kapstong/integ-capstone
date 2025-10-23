<?php
/**
 * ATIERA Financial Management System - Workflow Processor Cron Job
 * Processes scheduled workflow steps and handles timeouts
 *
 * This script should be run periodically (e.g., every 5-15 minutes) via cron:
 * * /5 * * * * /usr/bin/php /path/to/capstone-new/cron/workflow_processor.php
 */

require_once '../includes/workflow.php';
require_once '../includes/logger.php';

try {
    $workflowEngine = WorkflowEngine::getInstance();
    $logger = Logger::getInstance();

    $logger->info("Starting workflow processor cron job");

    // Process scheduled workflow steps
    $scheduledProcessed = $workflowEngine->processScheduledSteps();

    // Process timed-out workflow steps
    $timeoutProcessed = $workflowEngine->processTimedOutSteps();

    $logger->info("Workflow processor completed", [
        'scheduled_processed' => $scheduledProcessed,
        'timeout_processed' => $timeoutProcessed
    ]);

    // Output for cron logging
    echo "Workflow processor completed successfully\n";
    echo "Scheduled steps processed: $scheduledProcessed\n";
    echo "Timed-out steps processed: $timeoutProcessed\n";

} catch (Exception $e) {
    $logger = Logger::getInstance();
    $logger->error("Workflow processor failed", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    // Output error for cron logging
    echo "ERROR: Workflow processor failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
