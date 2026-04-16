<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = app('db')->table('legacy_student_payments')
    ->where('student_name', 'like', '%ZAYRAA%')
    ->orWhere('student_name', 'like', "%A'ISY%")
    ->orderByDesc('id')
    ->get();

echo "Rows:\n";
foreach ($rows as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
}

