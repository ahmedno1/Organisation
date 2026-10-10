<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Family;

$nationalId = $argv[1] ?? null;
if (! $nationalId) {
    fwrite(STDERR, "Usage: php scripts/lookup_family.php <head_national_id>\n");
    exit(1);
}

$id = Family::query()->where('head_national_id', $nationalId)->value('id');
if (! $id) {
    fwrite(STDERR, "NOT_FOUND\n");
    exit(2);
}

echo $id."\n";
