<?php
// Force errors to show up on the screen
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config/db.php';

try {
    $query = new MongoDB\Driver\Query([]); 
    // This line might fail if the extension isn't loaded
    $rows = $m_manager->executeQuery('happypawsvet.logs', $query);

    echo "<h2>System Activity Logs (From MongoDB)</h2>";
    echo "<table border='1'><tr><th>Event</th><th>MySQL Ref ID</th><th>Timestamp</th><th>Device</th></tr>";

    $count = 0;
    foreach ($rows as $row) {
        $count++;
        echo "<tr>
                <td>" . ($row->event ?? 'N/A') . "</td>
                <td>" . ($row->mysql_id ?? 'N/A') . "</td>
                <td>" . ($row->timestamp ?? 'N/A') . "</td>
                <td>" . ($row->user_agent ?? 'N/A') . "</td>
              </tr>";
    }

    if ($count === 0) {
        echo "<tr><td colspan='4'>No logs found in MongoDB yet. Go add an appointment first!</td></tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<div style='color:red; border:1px solid red; padding:10px;'>";
    echo "<strong>MongoDB Error:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "<br><a href='index.php'>Back to Dashboard</a>";
?>