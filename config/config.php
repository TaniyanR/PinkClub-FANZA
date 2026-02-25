<?php

declare(strict_types=1);

const APP_NAME = 'PinkClub FANZA';
const BASE_URL = '/pinkclub-fanza';
const LOGIN_PATH = BASE_URL . '/public/login0718.php';
const ADMIN_HOME_PATH = BASE_URL . '/admin/index.php';

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'dbname' => 'pinkclub_fanza',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'session_name' => 'pinkclub_fanza_session',
    ],
    'dmm' => [
        'endpoint' => 'https://api.dmm.com/affiliate/v3/',
        'site' => 'FANZA',
    ],
    'pagination' => [
        'per_page' => 20,
    ],
];
