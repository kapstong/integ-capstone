<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user']['id'];
$flashMessage = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_task') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $priority = strtolower(trim((string) ($_POST['priority'] ?? 'medium')));
    $dueDate = trim((string) ($_POST['due_date'] ?? ''));
    $assignedTo = (int) ($_POST['assigned_to'] ?? 0);

    $allowedPriorities = ['low', 'medium', 'high', 'urgent'];
    if ($title === '' || $assignedTo <= 0) {
        $flashMessage = 'Task title and assignee are required.';
        $flashType = 'danger';
    } elseif (!in_array($priority, $allowedPriorities, true)) {
        $flashMessage = 'Invalid task priority.';
        $flashType = 'danger';
    } else {
        $stmt = $db->prepare("
            SELECT id
            FROM users
            WHERE id = ? AND status = 'active' AND role = 'staff'
            LIMIT 1
        ");
        $stmt->execute([$assignedTo]);
        $assignee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assignee) {
            $flashMessage = 'Invalid assignee. You can only assign tasks to active staff.';
            $flashType = 'danger';
        } else {
            $dueDateValue = null;
            if ($dueDate !== '') {
                $parsed = date_create($dueDate);
                if ($parsed === false) {
                    $flashMessage = 'Invalid due date.';
                    $flashType = 'danger';
                } else {
                    $dueDateValue = date_format($parsed, 'Y-m-d');
                }
            }

            if ($flashMessage === '') {
                $stmt = $db->prepare("
                    INSERT INTO tasks (title, description, priority, status, due_date, assigned_to, created_by, category, created_at)
                    VALUES (?, ?, ?, 'pending', ?, ?, ?, 'manual', NOW())
                ");
                $stmt->execute([
                    $title,
                    $description !== '' ? $description : null,
                    $priority,
                    $dueDateValue,
                    $assignedTo,
                    $userId
                ]);

                $flashMessage = 'Task assigned successfully.';
                $flashType = 'success';
            }
        }
    }
}

$staffUsersStmt = $db->prepare("
    SELECT id, username, full_name
    FROM users
    WHERE status = 'active' AND role = 'staff'
    ORDER BY COALESCE(NULLIF(full_name, ''), username) ASC
");
$staffUsersStmt->execute();
$staffUsers = $staffUsersStmt->fetchAll(PDO::FETCH_ASSOC);

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

$assignedByMeStmt = $db->prepare("
    SELECT t.id, t.title, t.priority, t.status, t.due_date, t.created_at,
           COALESCE(NULLIF(u.full_name, ''), u.username) AS assignee_name
    FROM tasks t
    INNER JOIN users u ON u.id = t.assigned_to
    WHERE t.created_by = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$assignedByMeStmt->execute([$userId]);
$assignedByMe = $assignedByMeStmt->fetchAll(PDO::FETCH_ASSOC);

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
        .sidebar {
            height: 100vh;
            max-height: 100vh;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 2rem;
            background-color: #1e2936;
            color: white;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 300px;
            z-index: 1000;
            transition: transform 0.3s ease, width 0.3s ease;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        .sidebar.sidebar-collapsed {
            width: 120px;
        }
        .sidebar.sidebar-collapsed span {
            display: none;
        }
        .sidebar.sidebar-collapsed .nav-link {
            padding: 10px;
            text-align: center;
        }
        .sidebar.sidebar-collapsed .navbar-brand {
            text-align: center;
        }
        .sidebar.sidebar-collapsed .nav-item .dropdown-toggle {
            display: none;
        }
        .sidebar.sidebar-collapsed .submenu {
            display: none;
        }
        .sidebar .nav-link {
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        .sidebar .nav-link i {
            font-size: 1.4em;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .sidebar .submenu {
            padding-left: 20px;
        }
        .sidebar .submenu .nav-link {
            padding: 5px 20px;
            font-size: 0.9em;
        }
        .sidebar .nav-item {
            position: relative;
        }
        .sidebar .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        .sidebar .nav-item i[data-bs-toggle="collapse"] {
            position: absolute;
            right: 20px;
            top: 10px;
            transition: transform 0.3s ease;
        }
        .sidebar .nav-item i[aria-expanded="true"] {
            transform: rotate(90deg);
        }
        .sidebar .nav-item i[aria-expanded="false"] {
            transform: rotate(0deg);
        }
        .content {
            margin-left: 300px;
            padding: 20px;
            transition: margin-left 0.3s ease;
            position: relative;
            z-index: 1;
        }
        .sidebar-toggle {
            position: fixed;
            left: 290px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: white;
            font-size: 1.5em;
            width: 40px;
            height: 40px;
            background-color: #1e2936;
            border: 2px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: left 0.3s ease, background-color 0.3s ease;
            z-index: 1001;
        }
        .sidebar-toggle:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .toggle-btn {
            display: none;
        }
        .navbar .dropdown-toggle {
            text-decoration: none !important;
        }
        .navbar .dropdown-toggle:focus {
            box-shadow: none;
        }
        .navbar .btn-link {
            text-decoration: none !important;
        }
        .navbar .btn-link:focus {
            box-shadow: none;
        }
        .navbar {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e3e6ea;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 10000;
        }
        .navbar-brand {
            font-weight: 700;
            color: #2c3e50 !important;
            font-size: 1.4rem;
            letter-spacing: -0.02em;
        }
        .navbar .dropdown-toggle {
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            transition: all 0.2s ease;
        }
        .navbar .dropdown-toggle:hover {
            background-color: rgba(0,0,0,0.05);
        }
        .navbar .dropdown-toggle span {
            font-weight: 600;
            font-size: 1.1rem;
            color: #495057;
        }
        .navbar .btn-link {
            font-size: 1.1rem;
            border-radius: 8px;
            padding: 0.5rem;
            transition: all 0.2s ease;
            color: #6c757d;
        }
        .navbar .btn-link:hover {
            background-color: rgba(0,0,0,0.05);
            color: #495057;
        }
        .navbar .input-group {
            border-radius: 12px;
            overflow: hidden;
        }
        .navbar .form-control {
            border: 1px solid #dee2e6;
            padding: 0.5rem 1rem;
        }
        .navbar .btn-outline-secondary {
            border: 1px solid #dee2e6;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(30, 41, 54, 0.08);
        }
        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .content {
                margin-left: 0;
                padding: 20px;
            }
            .toggle-btn {
                display: block;
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

            <?php if ($flashMessage !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flashType); ?> mb-4" role="alert">
                    <?php echo htmlspecialchars($flashMessage); ?>
                </div>
            <?php endif; ?>

            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">Assign Task to Staff</h5>
                    <form method="post" class="row g-3">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="action" value="assign_task">
                        <div class="col-md-6">
                            <label for="assigned_to" class="form-label">Assignee</label>
                            <select id="assigned_to" name="assigned_to" class="form-select" required>
                                <option value="">Select staff member</option>
                                <?php foreach ($staffUsers as $staff): ?>
                                    <option value="<?php echo (int) $staff['id']; ?>">
                                        <?php echo htmlspecialchars(($staff['full_name'] ?: $staff['username']) . ' (' . $staff['username'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="priority" class="form-label">Priority</label>
                            <select id="priority" name="priority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="title" class="form-label">Task Title</label>
                            <input type="text" id="title" name="title" class="form-control" maxlength="255" required>
                        </div>
                        <div class="col-md-4">
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="date" id="due_date" name="due_date" class="form-control">
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Assign Task
                            </button>
                        </div>
                    </form>
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
                    <h5 class="mb-3">Tasks Assigned to You</h5>
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

            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h5 class="mb-3">Recently Assigned by You</h5>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Assignee</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($assignedByMe)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No assigned tasks yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($assignedByMe as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                                            <td><?php echo htmlspecialchars($row['assignee_name']); ?></td>
                                            <td><span class="badge <?php echo statusBadge($row['status']); ?>">
                                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['status']))); ?>
                                            </span></td>
                                            <td><?php echo htmlspecialchars(ucfirst($row['priority'] ?? 'medium')); ?></td>
                                            <td><?php echo htmlspecialchars($row['due_date'] ?: 'N/A'); ?></td>
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
