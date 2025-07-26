<?php
header('Content-Type: application/json');

$location = $_GET['location'] ?? '';
$fees = [
    'Island' => 2000,
    'Mainland' => 1500,
    'Inter-state (park)' => 3000,
    'Inter-state (doorstep)' => 5000,
    'Pick-up' => 0
];

$fee = $fees[$location] ?? 0;
echo json_encode(['fee_amount' => $fee]);
?>