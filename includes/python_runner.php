<?php
// Helper: includes/python_runner.php
// Small wrapper to execute a Python script using a full Python executable path (recommended: project venv).
// Usage:
//   require_once __DIR__ . '/python_runner.php';
//   $res = run_python_script(__DIR__ . '/../scripts/my_script.py', ['arg1','arg2'], 30);
//   if ($res['exit_code'] === 0) echo $res['stdout']; else error_log($res['stderr']);

function get_python_executable_path(): ?string {
    // Try project venv first (relative to repo root)
    $candidate = realpath(__DIR__ . '/../.venv/Scripts/python.exe');
    if ($candidate && is_file($candidate) && is_executable($candidate)) {
        return $candidate;
    }

    // Respect an environment override if present
    $env = getenv('PYTHON_EXECUTABLE');
    if ($env && is_file($env)) {
        return $env;
    }

    // Fallback to system `python` (may not be available to the webserver user)
    return 'python';
}

function run_python_script(string $scriptPath, array $args = [], int $timeout = 30): array {
    $python = get_python_executable_path();
    if (!$python) {
        return ['exit_code' => -1, 'stdout' => '', 'stderr' => 'Python executable not found'];
    }
    if (!is_file($scriptPath)) {
        return ['exit_code' => -1, 'stdout' => '', 'stderr' => 'Script not found: ' . $scriptPath];
    }

    $escapedPython = escapeshellcmd($python);
    $escapedScript = escapeshellarg($scriptPath);
    $escapedArgs = '';
    foreach ($args as $a) {
        $escapedArgs .= ' ' . escapeshellarg((string)$a);
    }

    $cmd = $escapedPython . ' ' . $escapedScript . $escapedArgs;

    $descriptorspec = [
        1 => ['pipe', 'w'], // stdout
        2 => ['pipe', 'w'], // stderr
    ];

    $proc = proc_open($cmd, $descriptorspec, $pipes);
    if (!is_resource($proc)) {
        return ['exit_code' => -1, 'stdout' => '', 'stderr' => 'Unable to start process'];
    }

    // non-blocking reads
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $start = time();

    while (true) {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        $status = proc_get_status($proc);
        if (!$status['running']) break;

        if ((time() - $start) > $timeout) {
            proc_terminate($proc);
            $stderr .= "\nProcess timed out after {$timeout} seconds.";
            break;
        }

        usleep(100000); // 100ms
    }

    foreach ($pipes as $p) {
        if (is_resource($p)) fclose($p);
    }

    $exit = proc_close($proc);

    return [
        'exit_code' => $exit,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'cmd' => $cmd,
    ];
}
