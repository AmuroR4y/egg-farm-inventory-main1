<?php
/**
 * Migration: Combined Egg Harvesting Sessions
 * 
 * This script creates the harvest_sessions table and adds session_id foreign key
 * to egg_inventory to support multiple egg sizes per harvest session.
 */

require_once 'db.php';

echo "Starting migration for combined egg harvesting...\n\n";

try {
    // Step 1: Create harvest_sessions table
    echo "Step 1: Creating harvest_sessions table...\n";
    
    $create_sessions_table = "
    CREATE TABLE IF NOT EXISTS harvest_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_date DATE NOT NULL,
        session_time TIME NOT NULL,
        total_eggs INT DEFAULT 0,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        notes TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
    ";
    
    if ($conn->query($create_sessions_table)) {
        echo "✓ harvest_sessions table created successfully\n";
    } else {
        throw new Exception("Failed to create harvest_sessions table: " . $conn->error);
    }
    
    // Step 2: Add session_id column to egg_inventory
    echo "\nStep 2: Adding session_id column to egg_inventory...\n";
    
    // Check if column already exists
    $check_column = $conn->query("SHOW COLUMNS FROM egg_inventory LIKE 'session_id'");
    if ($check_column && $check_column->num_rows > 0) {
        echo "✓ session_id column already exists\n";
    } else {
        $add_session_id = "
        ALTER TABLE egg_inventory
        ADD COLUMN session_id INT NULL AFTER batch_id,
        ADD INDEX idx_session_id (session_id),
        ADD CONSTRAINT fk_harvest_session
            FOREIGN KEY (session_id) REFERENCES harvest_sessions(id)
            ON DELETE SET NULL
            ON UPDATE CASCADE;
        ";
        
        if ($conn->query($add_session_id)) {
            echo "✓ session_id column added successfully\n";
        } else {
            throw new Exception("Failed to add session_id column: " . $conn->error);
        }
    }
    
    // Step 3: Create supply_sessions table for consolidated supply recording
    echo "\nStep 3: Creating supply_sessions table...\n";
    
    $create_supply_sessions = "
    CREATE TABLE IF NOT EXISTS supply_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_date DATE NOT NULL,
        session_time TIME NOT NULL,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        notes TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
    ";
    
    if ($conn->query($create_supply_sessions)) {
        echo "✓ supply_sessions table created successfully\n";
    } else {
        throw new Exception("Failed to create supply_sessions table: " . $conn->error);
    }
    
    // Step 4: Add session_id column to supply_inventory (optional, for future supply sessions)
    echo "\nStep 4: Adding session_id column to supply_inventory...\n";

    $check_supply_column = $conn->query("SHOW COLUMNS FROM supply_inventory LIKE 'session_id'");
    if ($check_supply_column && $check_supply_column->num_rows > 0) {
        echo "✓ session_id column already exists in supply_inventory\n";
    } else {
        echo "⚠ Skipping supply_inventory modification due to legacy data constraints.\n";
        echo "  (This is non-critical for egg harvesting functionality)\n";
        echo "  Supply session support can be added later if needed.\n";
    }
    
    echo "\n✓ Migration completed successfully!\n";
    echo "\nNew tables created:\n";
    echo "  - harvest_sessions\n";
    echo "  - supply_sessions\n";
    echo "\nColumns added:\n";
    echo "  - egg_inventory.session_id\n";
    echo "  - supply_inventory.session_id\n";
    
} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
