<?php

declare(strict_types=1);

use App\Support\Env;

return [
    'project_id' => Env::get('FIREBASE_PROJECT_ID', ''),
    'credentials_path' => Env::get('FIREBASE_CREDENTIALS_PATH', 'storage/credentials/firebase-service-account.json'),
];
