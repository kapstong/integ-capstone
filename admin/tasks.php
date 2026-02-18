<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user']['id'];

$task = null;
if (!empty($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND assigned_to = ? LIMIT 1");
    $stmt->execute([$_GET['id'], $userId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
}

$stmt = $db->prepare("
    SELECT id, title, description, priority, status, due_date, created_at
    FROM tasks
    WHERE assigned_to = ?
    ORDER BY FIELD(status,'pending','in_progress','completed','cancelled'), due_date IS NULL, due_date ASC, created_at DESC
");
$stmt->execute([$userId]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

function statusBadge($status) {
    $map = [
        'pending' => 'bg-warning text-dark',
        'in_progress' => 'bg-info',
        'completed' => 'bg-success',
        'cancelled' => 'bg-secondary'
    ];
    return $map[$status] ?? 'bg-secondary';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks</title>
    <link rel="icon" type="image/png" href="../logo2.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #F1F7EE;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        .content {
            margin-left: 300px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 992px) {
            .content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_navigation.php'; ?>

    <div class="content">
        <?php include '../includes/global_navbar.php'; ?>

        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="mb-1">My Tasks</h3>
                    <p class="text-muted mb-0">Tasks assigned to you.</p>
                </div>
            </div>

            <?php if ($task): ?>
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                            <h5 class="mb-0"><?php echo htmlspecialchars($task['title']); ?></h5>
                            <span class="badge <?php echo statusBadge($task['status']); ?>">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $task['status']))); ?>
                            </span>
                        </div>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($task['description'] ?? 'No description.')); ?></p>
                        <div class="d-flex flex-wrap gap-3">
                            <div><strong>Priority:</strong> <?php echo htmlspecialchars(ucfirst($task['priority'] ?? 'medium')); ?></div>
                            <div><strong>Due:</strong> <?php echo htmlspecialchars($task['due_date'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tasks)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No tasks assigned.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($tasks as $row): ?>
                                        <tr>
                                            <td>
                                                <a href="tasks.php?id=<?php echo (int)$row['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($row['title']); ?>
                                                </a>
                                            </td>
                                            <td><span class="badge <?php echo statusBadge($row['status']); ?>">
                                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['status']))); ?>
                                            </span></td>
                                            <td><?php echo htmlspecialchars(ucfirst($row['priority'] ?? 'medium')); ?></td>
                                            <td><?php echo htmlspecialchars($row['due_date'] ?: 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars(date('M j, Y', strtotime($row['created_at']))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
