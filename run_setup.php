<?php
/**
 * ATIERA Financial Management System - Complete Setup Runner
 * Runs all necessary setup scripts in the correct order
 */

echo "🚀 ATIERA Complete Setup Runner\n";
echo "===============================\n\n";

$startTime = microtime(true);

// Define setup scripts in order of execution
$setupScripts = [
    [
        'name' => 'Database Creation',
        'file' => 'create_database.php',
        'description' => 'Creates the main database'
    ],
    [
        'name' => 'Core Tables Setup',
        'file' => 'setup_database.php',
        'description' => 'Creates core tables (users, roles, permissions, etc.)'
    ],
    [
        'name' => 'API Tables Creation',
        'file' => 'create_api_tables.php',
        'description' => 'Creates API-related database tables'
    ],
    [
        'name' => 'Hotel/Restaurant Extension',
        'file' => 'setup_hotel_restaurant.php',
        'description' => 'Adds hotel and restaurant functionality'
    ],
    [
        'name' => 'Financials Extension',
        'file' => 'setup_financials_extension.php',
        'description' => 'Adds financial management extensions'
    ]
];

$completedScripts = 0;
$failedScripts = 0;

echo "📋 Setup Scripts to Run:\n";
foreach ($setupScripts as $i => $script) {
    echo "   " . ($i + 1) . ". {$script['name']} - {$script['description']}\n";
}
echo "\n";

foreach ($setupScripts as $script) {
    echo "🔧 Running {$script['name']} ({$script['description']})...\n";

    $scriptPath = __DIR__ . '/' . $script['file'];

    if (!file_exists($scriptPath)) {
        echo "❌ Script file not found: {$script['file']}\n\n";
        $failedScripts++;
        continue;
    }

    try {
        // Include the script (assumes they handle their own execution)
        ob_start();
        include $scriptPath;
        $output = ob_get_clean();

        if (!empty($output)) {
            echo "   Output: $output";
        }

        echo "✅ Completed: {$script['name']}\n\n";
        $completedScripts++;

    } catch (Exception $e) {
        echo "❌ Failed: {$script['name']} - " . $e->getMessage() . "\n\n";
        $failedScripts++;
    }
}

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "📊 SETUP SUMMARY\n";
echo "===============\n";
echo "Completed: $completedScripts\n";
echo "Failed: $failedScripts\n";
echo "Total Time: {$duration}s\n\n";

if ($failedScripts === 0) {
    echo "🎉 ALL SETUP SCRIPTS COMPLETED SUCCESSFULLY!\n\n";
    echo "💡 Next Steps:\n";
    echo "   1. Access admin panel: http://localhost/integ-capstone/admin/\n";
    echo "   2. Login with admin/admin123 (change default password!)\n";
    echo "   3. Configure your settings in the admin panel\n";
    echo "   4. Run the diagnostic script to verify: system_diagnostic.php\n\n";
} else {
    echo "❌ SOME SETUP SCRIPTS FAILED\n";
    echo "   Please check the error messages above and fix any issues before proceeding.\n\n";
}

echo str_repeat("=", 50) . "\n";
echo "Setup completed at " . date('Y-m-d H:i:s') . "\n";
?>
