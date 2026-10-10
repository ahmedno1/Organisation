<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Family;
use App\Models\Institution;
use App\Models\User;

$admin = User::query()->where('email', 'admin@example.com')->first();
if (! $admin) {
    fwrite(STDERR, "NO_ADMIN\n");
    exit(1);
}

$institution = $admin->institution_id
    ? Institution::query()->find($admin->institution_id)
    : Institution::query()->firstOrCreate(['name' => 'Perf Institution']);

if (! $admin->institution_id) {
    $admin->institution_id = $institution->id;
    $admin->save();
}

$manual = Family::query()->create([
    'institution_id' => $institution->id,
    'administrator_id' => $admin->id,
    'head_name' => 'Manual Test Family',
    'head_national_id' => 'MT-1001',
    'family_members_count' => 1,
]);

for ($i = 0; $i < 200; $i++) {
    Family::query()->create([
        'institution_id' => $institution->id,
        'administrator_id' => $admin->id,
        'head_name' => "Perf Family {$i}",
        'head_national_id' => 'PF-'.(10000 + $i),
        'family_members_count' => 1,
    ]);
}

echo "MANUAL_ID={$manual->id}\n";
