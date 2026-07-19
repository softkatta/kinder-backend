<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$emails = [
    'teacher@littlestars.com' => ['teacher', '/api/v1/dashboard/teacher'],
    'parent@littlestars.com' => ['parent', '/api/v1/dashboard/parent'],
    'student@littlestars.com' => ['student', '/api/v1/dashboard/student'],
    'guest@littlestars.com' => ['guest', '/api/v1/dashboard/guest'],
    'LS-GUEST-DEMO001' => ['guest', '/api/v1/dashboard/guest'],
];

$ok = true;
foreach ($emails as $email => [$role, $dashPath]) {
    $login = Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], json_encode(['email' => $email, 'password' => 'password']));
    $loginRes = $kernel->handle($login);
    $kernel->terminate($login, $loginRes);
    $loginBody = json_decode($loginRes->getContent(), true);

    if ($loginRes->getStatusCode() !== 200 || empty($loginBody['data']['token'])) {
        echo "LOGIN FAIL $email status=".$loginRes->getStatusCode()."\n";
        $ok = false;
        continue;
    }

    $roles = $loginBody['data']['roles'] ?? [];
    if (! in_array($role, $roles, true)) {
        echo "ROLE FAIL $email expected=$role got=".implode(',', $roles)."\n";
        $ok = false;
    }

    $token = $loginBody['data']['token'];
    Illuminate\Support\Facades\Auth::forgetGuards();

    $dash = Illuminate\Http\Request::create($dashPath, 'GET', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    ]);
    $dashRes = $kernel->handle($dash);
    $kernel->terminate($dash, $dashRes);
    if ($dashRes->getStatusCode() !== 200) {
        echo "DASHBOARD FAIL $email path=$dashPath status=".$dashRes->getStatusCode().' body='.$dashRes->getContent()."\n";
        $ok = false;
    } else {
        echo "OK $email login+dashboard\n";
    }
}

exit($ok ? 0 : 1);
