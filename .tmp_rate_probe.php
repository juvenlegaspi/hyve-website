<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pricing = app(\App\Support\HyvePricing::class);
$date = now()->addDay()->toDateString();

$probes = [
    'Room 1' => ['16:00-20:00', '19:00-21:00', '20:00-22:00', '01:00-03:00'],
    'Conference Room' => ['16:00-20:00', '19:00-21:00', '20:00-22:00'],
];

$results = [];

foreach ($probes as $roomName => $ranges) {
    $room = \App\Models\HyveRoom::query()->where('room_name', $roomName)->first();

    foreach ($ranges as $range) {
        [$start, $end] = explode('-', $range);
        $results[$roomName][$range] = $pricing->quoteForRoom($room, $date, $start, $end);
    }
}

echo json_encode($results, JSON_PRETTY_PRINT);
