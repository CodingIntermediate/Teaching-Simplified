<?php
require_once '../../config.php';
Auth::requireRole('admin');

$log_file = __DIR__ . '/../../logs/error.log';
$logs = [];

if (file_exists($log_file)) {
    $content = file_get_contents($log_file);
    $logs = array_reverse(explode("\n", $content));
    $logs = array_filter($logs);
}

// Clear logs
if (isset($_GET['clear'])) {
    file_put_contents($log_file, '');
    setFlash('success', 'Logs cleared successfully');
    redirect('modules/admin/error_logs.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Logs - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-bug"></i> Error Logs
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list"></i> System Error Logs</h5>
                <a href="?clear=1" class="btn btn-light btn-sm" onclick="return confirm('Clear all logs?')">
                    <i class="fas fa-trash"></i> Clear Logs
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($logs)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> No errors found. System is running smoothly!
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Error Message</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $index => $log): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td class="text-danger"><?php echo htmlspecialchars($log); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> System Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>PHP Version:</strong> <?php echo phpversion(); ?><br>
                        <strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?><br>
                        <strong>MySQL Version:</strong> <?php 
                            $db = db();
                            $result = $db->select("SELECT VERSION() as version");
                            echo $result[0]['version'];
                        ?>
                    </div>
                    <div class="col-md-6">
                        <strong>Upload Max Size:</strong> <?php echo ini_get('upload_max_filesize'); ?><br>
                        <strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?><br>
                        <strong>Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?> seconds
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>