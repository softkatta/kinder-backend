<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$emails = [
    'teacher@littlestars.com' => 'teacher',
    'parent@littlestars.com' => 'parent',
    'student@littlestars.com' => 'student',
];

$ok = true;
foreach ($emails as $email => $expectedRole) {
    $user = User::with('roles')->where('email', $email)->first();
    if (! $user) {
        echo "MISSING: $email\n";
        $ok = false;
        continue;
    }
    $roles = $user->roleNames();
    $passOk = Hash::check('password', $user->password);
    $roleOk = in_array($expectedRole, $roles, true);
    $active = $user->is_active;
    $status = ($passOk && $roleOk && $active) ? 'OK' : 'FAIL';
    echo "$status $email roles=[".implode(',', $roles)."] pass=".($passOk ? 'y' : 'n')." active=".($active ? 'y' : 'n')."\n";
    if ($status === 'FAIL') {
        $ok = false;
    }
}

exit($ok ? 0 : 1);
