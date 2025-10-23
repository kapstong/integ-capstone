<?php
/**
 * ATIERA Financial Management System - Backup and Recovery System
 * Handles database and file system backups with automated scheduling
 */

class BackupManager {
    private static $instance = null;
    private $db;
    private $backupDir;
    private $maxBackups = 30; // Keep maximum 30 backups
    private $compressionLevel = 6; // ZIP compression level

    private function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->backupDir = Config::get('backup.directory', __DIR__ . '/../backups');

        // Ensure backup directory exists
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create a complete database backup
     */
    public function createDatabaseBackup($name = null, $includeData = true) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backupName = $name ?: 'db_backup_' . $timestamp;
            $backupFile = $this->backupDir . '/' . $backupName . '.sql';

            // Get all tables
            $stmt = $this->db->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $backupContent = "-- ATIERA Financial Management System Database Backup\n";
            $backupContent .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
            $backupContent .= "-- Database: " . Config::get('database.name') . "\n\n";

            $backupContent .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

            foreach ($tables as $table) {
                // Create table structure
                $stmt = $this->db->query("SHOW CREATE TABLE `$table`");
                $createTable = $stmt->fetch(PDO::FETCH_ASSOC);

                $backupContent .= "-- Table structure for `$table`\n";
                $backupContent .= "DROP TABLE IF EXISTS `$table`;\n";
                $backupContent .= $createTable['Create Table'] . ";\n\n";

                if ($includeData) {
                    // Get table data
                    $stmt = $this->db->query("SELECT * FROM `$table`");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($rows)) {
                        $backupContent .= "-- Data for `$table`\n";
                        $backupContent .= "INSERT INTO `$table` VALUES\n";

                        $values = [];
                        foreach ($rows as $row) {
                            $rowValues = [];
                            foreach ($row as $value) {
                                if ($value === null) {
                                    $rowValues[] = 'NULL';
                                } else {
                                    $rowValues[] = $this->db->quote($value);
                                }
                            }
                            $values[] = '(' . implode(', ', $rowValues) . ')';
                        }

                        $backupContent .= implode(",\n", $values) . ";\n\n";
                    }
                }
            }

            $backupContent .= "SET FOREIGN_KEY_CHECKS = 1;\n";

            // Write backup file
            if (file_put_contents($backupFile, $backupContent) === false) {
                throw new Exception('Failed to write backup file');
            }

            // Compress the backup
            $compressedFile = $this->compressBackup($backupFile);
            if ($compressedFile) {
                unlink($backupFile); // Remove uncompressed file
                $backupFile = $compressedFile;
            }

            // Log the backup
            $this->logBackup('database', $backupName, $backupFile, filesize($backupFile));

            // Cleanup old backups
            $this->cleanupOldBackups('database');

            return [
                'success' => true,
                'file' => $backupFile,
                'size' => filesize($backupFile),
                'name' => $backupName
            ];

        } catch (Exception $e) {
            Logger::getInstance()->error("Database backup failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create a file system backup
     */
    public function createFilesystemBackup($name = null, $directories = null) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backupName = $name ?: 'fs_backup_' . $timestamp;
            $backupFile = $this->backupDir . '/' . $backupName . '.zip';

            // Default directories to backup
            if ($directories === null) {
                $directories = [
                    __DIR__ . '/../admin',
                    __DIR__ . '/../user',
                    __DIR__ . '/../includes',
                    __DIR__ . '/../templates',
                    __DIR__ . '/../uploads'
                ];
            }

            // Create ZIP archive
            $zip = new ZipArchive();
            if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('Failed to create ZIP archive');
            }

            foreach ($directories as $directory) {
                if (is_dir($directory)) {
                    $this->addDirectoryToZip($zip, $directory, basename($directory));
                }
            }

            $zip->close();

            // Log the backup
            $this->logBackup('filesystem', $backupName, $backupFile, filesize($backupFile));

            // Cleanup old backups
            $this->cleanupOldBackups('filesystem');

            return [
                'success' => true,
                'file' => $backupFile,
                'size' => filesize($backupFile),
                'name' => $backupName
            ];

        } catch (Exception $e) {
            Logger::getInstance()->error("Filesystem backup failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create a complete system backup (database + filesystem)
     */
    public function createFullBackup($name = null) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backupName = $name ?: 'full_backup_' . $timestamp;
            $backupDir = $this->backupDir . '/' . $backupName;

            // Create backup directory
            if (!mkdir($backupDir, 0755, true)) {
                throw new Exception('Failed to create backup directory');
            }

            // Create database backup
            $dbBackup = $this->createDatabaseBackup($backupName . '_db', true);
            if (!$dbBackup['success']) {
                throw new Exception('Database backup failed: ' . $dbBackup['error']);
            }

            // Move database backup to full backup directory
            $dbFile = $backupDir . '/' . basename($dbBackup['file']);
            if (!rename($dbBackup['file'], $dbFile)) {
                throw new Exception('Failed to move database backup');
            }

            // Create filesystem backup
            $fsBackup = $this->createFilesystemBackup($backupName . '_fs');
            if (!$fsBackup['success']) {
                throw new Exception('Filesystem backup failed: ' . $fsBackup['error']);
            }

            // Move filesystem backup to full backup directory
            $fsFile = $backupDir . '/' . basename($fsBackup['file']);
            if (!rename($fsBackup['file'], $fsFile)) {
                throw new Exception('Failed to move filesystem backup');
            }

            // Create manifest file
            $manifest = [
                'backup_name' => $backupName,
                'created_at' => date('Y-m-d H:i:s'),
                'type' => 'full',
                'database' => [
                    'file' => basename($dbFile),
                    'size' => filesize($dbFile)
                ],
                'filesystem' => [
                    'file' => basename($fsFile),
                    'size' => filesize($fsFile)
                ],
                'total_size' => filesize($dbFile) + filesize($fsFile)
            ];

            file_put_contents($backupDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

            // Compress the entire backup directory
            $compressedFile = $this->compressDirectory($backupDir);
            if ($compressedFile) {
                // Remove uncompressed directory
                $this->removeDirectory($backupDir);
                $backupFile = $compressedFile;
            } else {
                $backupFile = $backupDir;
            }

            // Log the backup
            $this->logBackup('full', $backupName, $backupFile, $this->getDirectorySize($backupFile));

            // Cleanup old backups
            $this->cleanupOldBackups('full');

            return [
                'success' => true,
                'file' => $backupFile,
                'size' => $this->getDirectorySize($backupFile),
                'name' => $backupName,
                'manifest' => $manifest
            ];

        } catch (Exception $e) {
            Logger::getInstance()->error("Full backup failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Restore database from backup
     */
    public function restoreDatabase($backupFile) {
        try {
            if (!file_exists($backupFile)) {
                throw new Exception('Backup file does not exist');
            }

            // Decompress if it's a ZIP file
            if (pathinfo($backupFile, PATHINFO_EXTENSION) === 'zip') {
                $extractedFile = $this->extractBackup($backupFile);
                if (!$extractedFile) {
                    throw new Exception('Failed to extract backup file');
                }
                $backupFile = $extractedFile;
            }

            // Read and execute SQL
            $sql = file_get_contents($backupFile);
            if ($sql === false) {
                throw new Exception('Failed to read backup file');
            }

            // Split SQL into individual statements
            $statements = array_filter(array_map('trim', explode(';', $sql)));

            $this->db->beginTransaction();

            foreach ($statements as $statement) {
                if (!empty($statement) && !preg_match('/^--/', $statement)) {
                    $this->db->exec($statement);
                }
            }

            $this->db->commit();

            // Log the restore
            Logger::getInstance()->logUserAction(
                'Database restored from backup',
                'backups',
                null,
                null,
                ['backup_file' => basename($backupFile)]
            );

            return ['success' => true, 'message' => 'Database restored successfully'];

        } catch (Exception $e) {
            $this->db->rollback();
            Logger::getInstance()->error("Database restore failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get list of available backups
     */
    public function getBackups($type = null) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM backups
                WHERE (? IS NULL OR type = ?)
                ORDER BY created_at DESC
            ");
            $stmt->execute([$type, $type]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to get backups: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get backup statistics
     */
    public function getBackupStats() {
        try {
            $stmt = $this->db->query("
                SELECT
                    COUNT(*) as total_backups,
                    COUNT(CASE WHEN type = 'database' THEN 1 END) as db_backups,
                    COUNT(CASE WHEN type = 'filesystem' THEN 1 END) as fs_backups,
                    COUNT(CASE WHEN type = 'full' THEN 1 END) as full_backups,
                    SUM(size_bytes) as total_size,
                    MAX(created_at) as last_backup
                FROM backups
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to get backup stats: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a backup
     */
    public function deleteBackup($backupId) {
        try {
            // Get backup info
            $stmt = $this->db->prepare("SELECT * FROM backups WHERE id = ?");
            $stmt->execute([$backupId]);
            $backup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$backup) {
                return ['success' => false, 'error' => 'Backup not found'];
            }

            // Delete file/directory
            if (is_dir($backup['file_path'])) {
                $this->removeDirectory($backup['file_path']);
            } elseif (file_exists($backup['file_path'])) {
                unlink($backup['file_path']);
            }

            // Delete from database
            $stmt = $this->db->prepare("DELETE FROM backups WHERE id = ?");
            $stmt->execute([$backupId]);

            Logger::getInstance()->logUserAction(
                'Deleted backup',
                'backups',
                $backupId,
                $backup,
                null
            );

            return ['success' => true, 'message' => 'Backup deleted successfully'];

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to delete backup: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify backup integrity
     */
    public function verifyBackup($backupId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM backups WHERE id = ?");
            $stmt->execute([$backupId]);
            $backup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$backup) {
                return ['success' => false, 'error' => 'Backup not found'];
            }

            $isValid = false;
            $details = [];

            if ($backup['type'] === 'database') {
                // Check if file exists and is readable
                $isValid = file_exists($backup['file_path']) && is_readable($backup['file_path']);
                $details['file_exists'] = $isValid;
                $details['file_size'] = $isValid ? filesize($backup['file_path']) : 0;

            } elseif ($backup['type'] === 'filesystem' || $backup['type'] === 'full') {
                if (is_dir($backup['file_path'])) {
                    $isValid = true;
                    $details['is_directory'] = true;
                    $details['directory_size'] = $this->getDirectorySize($backup['file_path']);
                } elseif (file_exists($backup['file_path'])) {
                    $isValid = true;
                    $details['is_file'] = true;
                    $details['file_size'] = filesize($backup['file_path']);
                }
            }

            return [
                'success' => true,
                'valid' => $isValid,
                'details' => $details
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Schedule automated backups
     */
    public function scheduleBackup($type, $frequency, $time = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO backup_schedules (type, frequency, scheduled_time, is_active, created_at)
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$type, $frequency, $time]);

            return ['success' => true, 'schedule_id' => $this->db->lastInsertId()];

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to schedule backup: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Run scheduled backups
     */
    public function runScheduledBackups() {
        try {
            $stmt = $this->db->query("
                SELECT * FROM backup_schedules
                WHERE is_active = 1
                AND (last_run IS NULL OR last_run < DATE_SUB(NOW(), INTERVAL frequency MINUTE))
            ");
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            foreach ($schedules as $schedule) {
                $result = null;

                switch ($schedule['type']) {
                    case 'database':
                        $result = $this->createDatabaseBackup();
                        break;
                    case 'filesystem':
                        $result = $this->createFilesystemBackup();
                        break;
                    case 'full':
                        $result = $this->createFullBackup();
                        break;
                }

                if ($result && $result['success']) {
                    // Update last run time
                    $stmt = $this->db->prepare("UPDATE backup_schedules SET last_run = NOW() WHERE id = ?");
                    $stmt->execute([$schedule['id']]);

                    $results[] = [
                        'schedule_id' => $schedule['id'],
                        'type' => $schedule['type'],
                        'success' => true,
                        'backup_name' => $result['name']
                    ];
                } else {
                    $results[] = [
                        'schedule_id' => $schedule['id'],
                        'type' => $schedule['type'],
                        'success' => false,
                        'error' => $result['error'] ?? 'Unknown error'
                    ];
                }
            }

            return ['success' => true, 'results' => $results];

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to run scheduled backups: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Private helper methods

    private function compressBackup($file) {
        try {
            $zipFile = $file . '.zip';
            $zip = new ZipArchive();

            if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
                return false;
            }

            $zip->addFile($file, basename($file));
            $zip->close();

            return $zipFile;

        } catch (Exception $e) {
            return false;
        }
    }

    private function compressDirectory($directory) {
        try {
            $zipFile = $directory . '.zip';
            $zip = new ZipArchive();

            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return false;
            }

            $this->addDirectoryToZip($zip, $directory, basename($directory));
            $zip->close();

            return $zipFile;

        } catch (Exception $e) {
            return false;
        }
    }

    private function addDirectoryToZip($zip, $directory, $zipPath) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($directory) + 1);
                $zip->addFile($filePath, $zipPath . '/' . $relativePath);
            }
        }
    }

    private function extractBackup($zipFile) {
        try {
            $extractTo = sys_get_temp_dir() . '/atiera_backup_' . time();
            mkdir($extractTo, 0755, true);

            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== true) {
                return false;
            }

            $zip->extractTo($extractTo);
            $zip->close();

            // Find the SQL file
            $sqlFiles = glob($extractTo . '/*.sql');
            return !empty($sqlFiles) ? $sqlFiles[0] : false;

        } catch (Exception $e) {
            return false;
        }
    }

    private function logBackup($type, $name, $file, $size) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO backups (type, name, file_path, size_bytes, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $type,
                $name,
                $file,
                $size,
                isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null
            ]);
        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to log backup: " . $e->getMessage());
        }
    }

    private function cleanupOldBackups($type) {
        try {
            // Get backups to delete (keep only the most recent maxBackups)
            $stmt = $this->db->prepare("
                SELECT id, file_path FROM backups
                WHERE type = ?
                ORDER BY created_at DESC
                LIMIT 999999 OFFSET ?
            ");
            $stmt->execute([$type, $this->maxBackups]);
            $oldBackups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($oldBackups as $backup) {
                // Delete file/directory
                if (is_dir($backup['file_path'])) {
                    $this->removeDirectory($backup['file_path']);
                } elseif (file_exists($backup['file_path'])) {
                    unlink($backup['file_path']);
                }

                // Delete from database
                $stmt = $this->db->prepare("DELETE FROM backups WHERE id = ?");
                $stmt->execute([$backup['id']]);
            }

        } catch (Exception $e) {
            Logger::getInstance()->error("Failed to cleanup old backups: " . $e->getMessage());
        }
    }

    private function removeDirectory($directory) {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($directory);
    }

    private function getDirectorySize($path) {
        if (is_file($path)) {
            return filesize($path);
        }

        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
?>
