<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $conn = new mysqli($host, $user, $pass);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get list of databases
    $db_res = $conn->query("SHOW DATABASES");
    $databases = [];
    while ($row = $db_res->fetch_row()) {
        $databases[] = $row[0];
    }

    echo "Available Databases on local MySQL server:\n";
    foreach ($databases as $db) {
        echo " - $db\n";
    }
    echo "\n";

    // Let's inspect databases containing "student" or "infinity" in their names
    foreach ($databases as $db) {
        if (stripos($db, 'student') !== false || stripos($db, 'infinity') !== false || $db === 'test') {
            $conn->select_db($db);
            $table_res = $conn->query("SHOW TABLES");
            $tables = [];
            while ($t_row = $table_res->fetch_row()) {
                $tables[] = $t_row[0];
            }

            if (in_array('engineer', $tables) || in_array('engineers', $tables)) {
                echo "FOUND: Table matching 'engineer' in database '$db'!\n";
                $engineer_table = in_array('engineer', $tables) ? 'engineer' : 'engineers';
                
                // Show create table statement
                $create_res = $conn->query("SHOW CREATE TABLE `$engineer_table`");
                $create_row = $create_res->fetch_row();
                echo "\nSchema of '$engineer_table' in '$db':\n";
                echo $create_row[1] . "\n\n";

                // Show data rows
                $data_res = $conn->query("SELECT * FROM `$engineer_table`");
                echo "Data inside '$engineer_table' in '$db':\n";
                while ($d_row = $data_res->fetch_assoc()) {
                    print_r($d_row);
                }
                echo "--------------------------------------------------\n";
            }
        }
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
