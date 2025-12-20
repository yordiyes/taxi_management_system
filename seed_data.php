<?php
require_once 'api/db.php';

$database = new Database();
$db = $database->getConnection();

echo "Seeding database...\n";

// 1. Seed Services
$services = [
    ['Standard Taxi', 'Reliable ride for everyday travel.', 5.00, 2.00],
    ['Premium Sedan', 'Luxury travel with extra comfort.', 10.00, 3.50],
    ['Family Van', 'Spacious van for groups and luggage.', 15.00, 4.00]
];

foreach ($services as $s) {
    $check = $db->prepare("SELECT id FROM services WHERE name = ?");
    $check->execute([$s[0]]);
    if ($check->rowCount() == 0) {
        $stmt = $db->prepare("INSERT INTO services (name, description, base_price, price_per_km) VALUES (?, ?, ?, ?)");
        $stmt->execute($s);
        echo "Added Service: {$s[0]}\n";
    }
}

// 2. Seed Vehicles
$vehicles = [
    ['Toyota', 'Camry', 'TAXI-001', 'Sedan', 4],
    ['Honda', 'Civic', 'TAXI-002', 'Sedan', 4],
    ['Mercedes', 'E-Class', 'PREM-001', 'Sedan', 4],
    ['Toyota', 'Sienna', 'VAN-001', 'Van', 7]
];

foreach ($vehicles as $v) {
    $check = $db->prepare("SELECT id FROM vehicles WHERE license_plate = ?");
    $check->execute([$v[2]]);
    if ($check->rowCount() == 0) {
        $stmt = $db->prepare("INSERT INTO vehicles (make, model, license_plate, type, capacity, status) VALUES (?, ?, ?, ?, ?, 'available')");
        $stmt->execute($v);
        echo "Added Vehicle: {$v[2]}\n";
    }
}

// 3. Seed Drivers
$drivers = [
    ['John Doe', 'LIC-1001', '555-0101'],
    ['Jane Smith', 'LIC-1002', '555-0102'],
    ['Bob Wilson', 'LIC-1003', '555-0103']
];

foreach ($drivers as $d) {
    $check = $db->prepare("SELECT id FROM drivers WHERE license_number = ?");
    $check->execute([$d[1]]);
    if ($check->rowCount() == 0) {
        $stmt = $db->prepare("INSERT INTO drivers (name, license_number, phone, status) VALUES (?, ?, ?, 'available')");
        $stmt->execute($d);
        echo "Added Driver: {$d[0]}\n";
    }
}

echo "Database seeding completed successfully.\n";
?>
