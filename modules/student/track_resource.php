<?php
require_once '../../config.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$db = db();
$user = Auth::getCurrentUser();

$resource_type = $_POST['resource_type'] ?? '';
$resource_id = $_POST['resource_id'] ?? 0;
$external_url = $_POST['external_url'] ?? '';

if ($resource_type && ($resource_id || $external_url)) {
    $db->insert('user_resource_interactions', [
        'user_id' => $user['id'],
        'resource_type' => $resource_type,
        'resource_id' => $resource_id,
        'external_url' => $external_url,
        'action' => 'click',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Update download count for internal resources
    if ($resource_type == 'internal' && $resource_id) {
        $db->query("UPDATE tutor_resources SET downloads = downloads + 1 WHERE id = ?", [$resource_id]);
    }
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
}
?>