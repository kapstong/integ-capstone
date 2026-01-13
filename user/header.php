<?php
/**
 * ATIERA Staff Portal - User Header Template
 * Common HTML head and navigation for all user pages
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?>ATIERA Staff Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../includes/enhanced-ui.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
    background: linear-gradient(135deg, #f8fafc 0%, #e8ecf7 100%);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
    margin: 0;
    padding: 0;
    min-height: 100vh;
}
.sidebar {
    background: linear-gradient(180deg, #0f1c49 0%, #1b2f73 50%, #15265e 100%);
    color: white;
    min-height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    width: 300px;
    z-index: 1000;
    transition: transform 0.3s ease, width 0.3s ease;
    box-shadow: 4px 0 20px rgba(15, 28, 73, 0.15);
    border-right: 2px solid rgba(212, 175, 55, 0.2);
}
.sidebar.sidebar-collapsed {
    width: 120px;
}
.sidebar.sidebar-collapsed span {
    display: none;
}
.sidebar.sidebar-collapsed .nav-link {
    padding: 12px;
    text-align: center;
    justify-content: center;
}
.sidebar.sidebar-collapsed .navbar-brand {
    text-align: center;
}
.sidebar .nav-link {
    color: rgba(255,255,255,0.85);
    padding: 14px 24px;
    border-radius: 12px;
    margin: 4px 16px;
    font-size: 1.05em;
    font-weight: 500;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    overflow: hidden;
}
.sidebar .nav-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: #d4af37;
    transform: scaleY(0);
    transition: transform 0.2s ease;
}
.sidebar .nav-link i {
    font-size: 1.3em;
    width: 24px;
    text-align: center;
}
.sidebar .nav-link:hover {
    background: rgba(255,255,255,0.12);
    color: white;
    transform: translateX(4px);
}
.sidebar .nav-link:hover::before {
    transform: scaleY(1);
}
.sidebar .nav-link.active {
    background: linear-gradient(135deg, #d4af37 0%, #b8961f 100%);
    color: #0f1c49;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
}
.sidebar .nav-link.active::before {
    display: none;
}
.sidebar .navbar-brand {
    color: white !important;
    font-weight: 800;
    padding: 24px 20px;
    border-bottom: 2px solid #d4af37;
    background: rgba(0, 0, 0, 0.2);
}
.sidebar .navbar-brand img {
    height: 50px;
    width: auto;
    max-width: 100%;
    transition: height 0.3s ease;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,0.3));
}
.sidebar.sidebar-collapsed .navbar-brand img {
    height: 70px;
}
.content {
    margin-left: 120px;
    padding: 20px;
    transition: margin-left 0.3s ease;
    position: relative;
    z-index: 1;
}
.sidebar-toggle {
    position: fixed;
    left: 110px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: white;
    font-size: 1.5em;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%);
    border: 2px solid #d4af37;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: left 0.3s ease, background-color 0.3s ease, transform 0.2s ease;
    z-index: 1001;
    box-shadow: 0 4px 12px rgba(15, 28, 73, 0.3);
}
.sidebar-toggle:hover {
    background: linear-gradient(135deg, #d4af37 0%, #b8961f 100%);
    color: #0f1c49;
    transform: translateY(-50%) scale(1.1);
}
.toggle-btn {
    display: none;
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
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}
.card:hover {
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}
.card-header {
    background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%);
    color: white;
    border-bottom: 3px solid #d4af37;
    border-radius: 12px 12px 0 0 !important;
    padding: 1.5rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(15, 28, 73, 0.1);
}
.card-body {
    padding: 2rem;
}
.btn {
    border-radius: 8px;
    font-weight: 600;
    padding: 0.5rem 1.5rem;
    transition: all 0.3s ease;
    border: none;
}
.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.btn-success {
    background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%);
    color: white;
    border: 1px solid rgba(212, 175, 55, 0.3);
}
.btn-success:hover {
    background: linear-gradient(135deg, #d4af37 0%, #b8961f 100%);
    color: #0f1c49;
    border-color: #d4af37;
}
.stats-card {
    background: linear-gradient(135deg, #1b2f73 0%, #0f1c49 100%);
    color: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(15, 28, 73, 0.2);
    border: 2px solid rgba(212, 175, 55, 0.2);
    transition: all 0.3s ease;
}
.stats-card:hover {
    border-color: #d4af37;
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.3);
    transform: translateY(-2px);
}
.stats-card h3 {
    font-size: 2em;
    margin-bottom: 5px;
}
.stats-card p {
    margin: 0;
    opacity: 0.9;
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
    .stats-card h3 {
        font-size: 1.5em;
    }
}
</style>
</head>
<body>
    <?php include_once __DIR__ . '/../includes/loading_screen.php'; ?>

    <!-- Global Sidebar -->
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white mb-4 shadow-sm">
        <div class="container-fluid">
            <button class="btn btn-outline-secondary toggle-btn" type="button" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <span class="navbar-brand mb-0 h1 me-4"><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Staff Dashboard'; ?></span>
            <div class="d-flex align-items-center me-4">
                <div class="dropdown">
                    <button class="btn btn-link text-dark dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <span><strong><?php echo htmlspecialchars($_SESSION['user']['username']); ?></strong></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../index.php?logout=1"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
            <div class="d-flex align-items-center flex-grow-1">
                <div class="input-group mx-auto" style="width: 500px;">
                    <input type="text" class="form-control" placeholder="Search..." aria-label="Search">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="content"></content></div>
</body>
</html>
