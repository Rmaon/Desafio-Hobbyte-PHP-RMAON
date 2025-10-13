<?php

declare(strict_types=1);


header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }


require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/controllers/controller.php';

use services\Database;

services\Database::connect();


$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uriParams = explode("/", trim($uri, "/"));

$method = $_SERVER['REQUEST_METHOD'];


if ($method === 'POST' && isset($_GET['_method'])) {
    $override = strtoupper((string)$_GET['_method']);
    if (in_array($override, ['PATCH','DELETE'])) {
        $method = $override;
    }
}


$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) { $input = []; }


$seg0 = $uriParams[0] ?? '';
$seg1 = $uriParams[1] ?? '';
$seg2 = $uriParams[2] ?? '';
$seg3 = $uriParams[3] ?? '';

switch ($seg0) {
    // ----------------------- AUTH -----------------------
    case 'auth':
        if ($seg1 === 'register' && $method === 'POST') { post_auth_register(); }
        if ($seg1 === 'login'    && $method === 'POST') { post_auth_login(); }
        if ($seg1 === 'logout'   && $method === 'POST') { post_auth_logout(); }
        break;

    // ----------------------- ADMIN ----------------------
    case 'admin':
        if ($seg1 === 'users' && $seg2 === '' && $method === 'GET')  { get_admin_users(); }
        if ($seg1 === 'users' && $seg2 === '' && $method === 'POST') { post_admin_users(); }

        // /admin/users/{id}/role  (PATCH)
        if ($seg1 === 'users' && preg_match('/^\d+$/', $seg2) && $seg3 === 'role' && $method === 'PATCH') {
            patch_admin_user_role((int)$seg2);
        }

        // /admin/users/{id} (DELETE)
        if ($seg1 === 'users' && preg_match('/^\d+$/', $seg2) && $seg3 === '' && $method === 'DELETE') {
            delete_admin_user((int)$seg2);
        }
        break;

    // ----------------------- USER -----------------------
    case 'user':
        if ($seg1 === 'me'       && $method === 'GET')  { get_user_me(); }
        if ($seg1 === 'password' && $method === 'POST') { post_user_password(); }
        if ($seg1 === 'stats'    && $method === 'GET')  { get_user_stats(); }
        break;

    // ----------------------- GAMER ----------------------
    case 'gamer':
        if ($seg1 === 'games' && $seg2 === '' && $method === 'POST') { post_gamer_games(); }

        // /gamer/games/{id}
        if ($seg1 === 'games' && preg_match('/^\d+$/', $seg2) && $seg3 === '' && $method === 'GET') {
            get_gamer_game((int)$seg2);
        }

        // /gamer/games/{id}/reveal
        if ($seg1 === 'games' && preg_match('/^\d+$/', $seg2) && $seg3 === 'reveal' && $method === 'POST') {
            post_gamer_reveal((int)$seg2);
        }

        // /gamer/games/{id}/surrender
        if ($seg1 === 'games' && preg_match('/^\d+$/', $seg2) && $seg3 === 'surrender' && $method === 'POST') {
            post_gamer_surrender((int)$seg2);
        }
        break;
}

// 404 por defecto
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Ruta no encontrada', 'method' => $method, 'uri' => $uri], JSON_UNESCAPED_UNICODE);
