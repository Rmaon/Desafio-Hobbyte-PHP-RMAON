<?php
use services\Database;



function json_response($data, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function method() { return $_SERVER['REQUEST_METHOD'] ?? 'GET'; }
function path()   { return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/'; }

function body_json() {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

/** Simula PATCH/DELETE vía POST con _method */
function effective_method() {
    if (method() === 'POST' && isset($_GET['_method'])) {
        $m = strtoupper($_GET['_method']);
        if (in_array($m, ['PATCH','DELETE'])) return $m;
    }
    return method();
}

// --------------------------- Auth con sesiones --------------------------------

function require_auth() {
    session_start();
    if (empty($_SESSION['user'])) json_response(['error'=>'No autenticado'], 401);
    return $_SESSION['user']; // ['idUsuario'=>..., 'email'=>..., 'role'=>'admin'|'player']
}

function require_role($role) {
    $u = require_auth();
    if (($u['role'] ?? '') !== $role) json_response(['error'=>'Prohibido'], 403);
    return $u;
}

// --------------------------- SQL auxiliares -----------------------------------

function db() { return services\Database::connect(); }

function q($sql, $params = []) {
    $cx = db();
    $stmt = mysqli_prepare($cx, $sql);
    if (!$stmt) json_response(['error'=>mysqli_error($cx)], 500);
    if ($params) {
        // types dinámicos
        $types = '';
        $vals  = [];
        foreach ($params as $p) {
            if (is_int($p)) $types .= 'i';
            elseif (is_float($p)) $types .= 'd';
            else { $types .= 's'; $p = (string)$p; }
            $vals[] = $p;
        }
        mysqli_stmt_bind_param($stmt, $types, ...$vals);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return $res ?: true;
}

function last_id() {
    return mysqli_insert_id(db());
}

// ----------------------------- AUTH Controllers -------------------------------

function post_auth_register() {
    $d = body_json();
    $email = trim($d['email'] ?? '');
    $password = (string)($d['password'] ?? '');
    $role = ($d['role'] ?? 'player') === 'admin' ? 'admin' : 'player';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        json_response(['error'=>'Datos inválidos'], 422);
    }
    // ¿existe?
    $res = q("SELECT idUsuario FROM usuario WHERE email = ?", [$email]);
    if ($row = mysqli_fetch_assoc($res)) json_response(['error'=>'Email ya registrado'], 409);

    $hash = password_hash($password, PASSWORD_BCRYPT);
    q("INSERT INTO usuario (email, hash, role) VALUES (?,?,?)", [$email, $hash, $role]);
    $id = last_id();
    json_response(['idUsuario'=>$id], 201);
}

function post_auth_login() {
    session_start();
    $d = body_json();
    $email = trim($d['email'] ?? '');
    $password = (string)($d['password'] ?? '');
    $res = q("SELECT idUsuario, email, role, hash FROM usuario WHERE email = ?", [$email]);
    $row = mysqli_fetch_assoc($res);
    if (!$row || !password_verify($password, $row['hash'])) {
        json_response(['error'=>'Credenciales inválidas'], 401);
    }
    $_SESSION['user'] = ['idUsuario'=>(int)$row['idUsuario'], 'email'=>$row['email'], 'role'=>$row['role']];
    json_response(['ok'=>true, 'user'=>$_SESSION['user']]);
}

function post_auth_logout() {
    session_start();
    session_destroy();
    json_response(['ok'=>true]);
}

// ----------------------------- ADMIN Controllers ------------------------------

function get_admin_users() {
    require_role('admin');
    $res = q("SELECT idUsuario, email, role, creado_en FROM usuario ORDER BY idUsuario DESC");
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    json_response($rows);
}

function post_admin_users() {
    require_role('admin');
    $d = body_json();
    $email = trim($d['email'] ?? '');
    $password = (string)($d['password'] ?? 'changeme');
    $role = ($d['role'] ?? 'player') === 'admin' ? 'admin' : 'player';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(['error'=>'Email inválido'], 422);
    $hash = password_hash($password, PASSWORD_BCRYPT);
    q("INSERT INTO usuario (email, hash, role) VALUES (?,?,?)", [$email, $hash, $role]);
    json_response(['idUsuario'=>last_id()], 201);
}

function patch_admin_user_role($id) {
    require_role('admin');
    $d = body_json();
    $role = ($d['role'] ?? 'player') === 'admin' ? 'admin' : 'player';
    q("UPDATE usuario SET role=? WHERE idUsuario=?", [$role, (int)$id]);
    json_response(['ok'=>true]);
}

function delete_admin_user($id) {
    require_role('admin');
    q("DELETE FROM usuario WHERE idUsuario=?", [(int)$id]);
    json_response(['ok'=>true]);
}

// ------------------------------ USER Controllers ------------------------------

function get_user_me() {
    $u = require_auth();
    $res = q("SELECT idUsuario, email, role, creado_en FROM usuario WHERE idUsuario=?", [$u['idUsuario']]);
    json_response(mysqli_fetch_assoc($res) ?: []);
}

function post_user_password() {
    $u = require_auth();
    $d = body_json();
    $password = (string)($d['password'] ?? '');
    if (strlen($password) < 6) json_response(['error'=>'Password demasiado corto'], 422);
    $hash = password_hash($password, PASSWORD_BCRYPT);
    q("UPDATE usuario SET hash=? WHERE idUsuario=?", [$hash, $u['idUsuario']]);
    json_response(['ok'=>true]);
}

function get_user_stats() {
    require_auth(); // stats generales del usuario (simplificado)
    $res = q("
        SELECT 
            SUM(j.estado='ganado') AS ganadas,
            SUM(j.estado='perdido') AS perdidas,
            SUM(j.estado='en_curso' OR j.estado='creado') AS abiertas
        FROM juego j
        JOIN juego_usuario ju ON ju.idJuego = j.idJuego
        WHERE ju.idUsuario = ?
    ", [$_SESSION['user']['idUsuario']]);
    json_response(mysqli_fetch_assoc($res) ?: ['ganadas'=>0,'perdidas'=>0,'abiertas'=>0]);
}

// ------------------------------ GAME Engine -----------------------------------

function pick_effort() {
    $r = mt_rand() / mt_getrandmax(); // 0..1
    $choices = [5,10,15,20,25,30,35,40,45,50];
    if ($r < 0.65)  return $choices[random_int(0,3)];      // 5..20
    if ($r < 0.95)  return $choices[4 + random_int(0,3)];  // 25..40
    return $choices[8 + random_int(0,1)];                  // 45..50
}
function pick_type() { return random_int(1,3); } // 1 magia, 2 fuerza, 3 habilidad

function create_board($cx, $idJuego, $filas, $columnas) {
    // Crear tablero
    q("INSERT INTO tablero (idJuego, filas, columnas) VALUES (?,?,?)", [$idJuego, $filas, $columnas]);
    $idTablero = last_id();
    for ($x=0; $x<$filas; $x++) {
        for ($y=0; $y<$columnas; $y++) {
            q("INSERT INTO casilla (idTablero, cordX, cordY, tipo, esfuerzo) VALUES (?,?,?,?,?)",
                [$idTablero, $x, $y, pick_type(), pick_effort()]);
        }
    }
    return $idTablero;
}

function ensure_round1($idJuego) {
    q("INSERT INTO ronda (idJuego, numero, perdidas_consecutivas) VALUES (?,1,0)", [$idJuego]);
    return last_id();
}

function create_heroes($idJuego) {
    q("INSERT INTO personaje (idJuego, nombre, tipo, poder_max, poder_actual, vivo) VALUES 
       (?,?,?,?,?,1), (?,?,?,?,?,1), (?,?,?,?,?,1)",
       [$idJuego,'Gandalf',1,50,50, $idJuego,'Thorin',2,50,50, $idJuego,'Bilbo',3,50,50]);
}

function current_round_id($idJuego) {
    $res = q("SELECT idRonda FROM ronda WHERE idJuego=? ORDER BY numero DESC LIMIT 1", [$idJuego]);
    $row = mysqli_fetch_assoc($res);
    return $row ? (int)$row['idRonda'] : 0;
}

function set_losses($idRonda, $reset) {
    if ($reset) q("UPDATE ronda SET perdidas_consecutivas=0 WHERE idRonda=?", [$idRonda]);
    else q("UPDATE ronda SET perdidas_consecutivas=perdidas_consecutivas+1 WHERE idRonda=?", [$idRonda]);
}

function eval_game_state($idJuego, $idTablero) {
    $rRes = q("SELECT idRonda, perdidas_consecutivas FROM ronda WHERE idJuego=? ORDER BY numero DESC LIMIT 1", [$idJuego]);
    $r = mysqli_fetch_assoc($rRes);
    $perdidas = (int)($r['perdidas_consecutivas'] ?? 0);

    $hRes = q("SELECT SUM(vivo=1) AS vivos FROM personaje WHERE idJuego=?", [$idJuego]);
    $vivos = (int)(mysqli_fetch_assoc($hRes)['vivos'] ?? 0);

    $bRes = q("SELECT SUM(estado='destapada') AS destapadas, COUNT(*) AS total FROM casilla WHERE idTablero=?", [$idTablero]);
    $b = mysqli_fetch_assoc($bRes);
    $destapadas = (int)$b['destapadas']; $total = (int)$b['total'];

    $estado = 'en_curso';
    if ($perdidas >= 5 || $vivos === 0) $estado = 'perdido';
    if ($destapadas >= (int)ceil($total/2) && $vivos >= 1) $estado = 'ganado';

    if ($estado !== 'en_curso') {
        q("UPDATE juego SET estado=? WHERE idJuego=?", [$estado, $idJuego]);
    } else {
        q("UPDATE juego SET estado='en_curso' WHERE idJuego=? AND estado='creado'", [$idJuego]);
        // avanzar ronda copiando perdidas_consecutivas
        q("INSERT INTO ronda (idJuego, numero, perdidas_consecutivas)
           SELECT ?, COALESCE(MAX(numero),0)+1, (SELECT perdidas_consecutivas FROM ronda WHERE idJuego=? ORDER BY numero DESC LIMIT 1)
           FROM ronda WHERE idJuego=?", [$idJuego,$idJuego,$idJuego]);
    }

    return ['estado'=>$estado, 'destapadas'=>$destapadas, 'total'=>$total, 'heroes_vivos'=>$vivos, 'perdidas_consecutivas'=>$perdidas];
}

// ----------------------------- GAMER Controllers ------------------------------

function post_gamer_games() {
    $u = require_auth();
    $d = body_json();
    $nombre = trim($d['nombre'] ?? 'Partida');
    $filas = max(1, (int)($d['filas'] ?? 4));
    $columnas = max(1, (int)($d['columnas'] ?? 5));

    // Limitar a 2 partidas abiertas del usuario
    $res = q("SELECT COUNT(*) c FROM juego j 
              JOIN juego_usuario ju ON ju.idJuego=j.idJuego
              WHERE ju.idUsuario=? AND j.estado IN ('creado','en_curso')", [$u['idUsuario']]);
    $c = (int)(mysqli_fetch_assoc($res)['c'] ?? 0);
    if ($c >= 2) json_response(['error'=>'Ya tienes 2 partidas abiertas'], 409);

    // Crear juego
    q("INSERT INTO juego (nombre, estado) VALUES (?, 'creado')", [$nombre]);
    $idJuego = last_id();
    q("INSERT INTO juego_usuario (idJuego, idUsuario) VALUES (?,?)", [$idJuego, $u['idUsuario']]);

    $idTablero = create_board(db(), $idJuego, $filas, $columnas);
    create_heroes($idJuego);
    $idRonda = ensure_round1($idJuego);

    json_response(['idJuego'=>$idJuego, 'idTablero'=>$idTablero, 'idRonda'=>$idRonda], 201);
}

function get_gamer_game($id) {
    $u = require_auth();
    // validar pertenencia
    $own = q("SELECT 1 FROM juego_usuario WHERE idJuego=? AND idUsuario=?", [(int)$id, $u['idUsuario']]);
    if (!mysqli_fetch_assoc($own)) json_response(['error'=>'No autorizado a ver esta partida'], 403);

    $j = q("SELECT * FROM juego WHERE idJuego=?", [(int)$id]);
    $juego = mysqli_fetch_assoc($j);
    if (!$juego) json_response(['error'=>'Partida no existe'], 404);

    $t = q("SELECT * FROM tablero WHERE idJuego=?", [(int)$id]);
    $tablero = mysqli_fetch_assoc($t);

    $pcs = [];
    $pRes = q("SELECT idPersonaje, nombre, tipo, poder_actual, vivo FROM personaje WHERE idJuego=? ORDER BY tipo", [(int)$id]);
    while ($r = mysqli_fetch_assoc($pRes)) $pcs[] = $r;

    $cells = [];
    $cRes = q("SELECT idCasilla, cordX, cordY, tipo, esfuerzo, estado FROM casilla WHERE idTablero=? ORDER BY idCasilla", [$tablero['idTablero']]);
    while ($r = mysqli_fetch_assoc($cRes)) $cells[] = $r;

    json_response(['juego'=>$juego, 'tablero'=>$tablero, 'personajes'=>$pcs, 'casillas'=>$cells]);
}

function post_gamer_reveal($id) {
    $u = require_auth();
    $d = body_json();
    $x = (int)($d['cordX'] ?? -1);
    $y = (int)($d['cordY'] ?? -1);

    // comprobar partida y propiedad
    $own = q("SELECT 1 FROM juego_usuario WHERE idJuego=? AND idUsuario=?", [(int)$id, $u['idUsuario']]);
    if (!mysqli_fetch_assoc($own)) json_response(['error'=>'No autorizado'], 403);

    $state = q("SELECT estado FROM juego WHERE idJuego=?", [(int)$id]);
    $rowState = mysqli_fetch_assoc($state);
    if (!$rowState) json_response(['error'=>'Partida no existe'], 404);
    if (!in_array($rowState['estado'], ['creado','en_curso'])) {
        json_response(['error'=>"Partida en estado {$rowState['estado']}"], 409);
    }

    $t = q("SELECT idTablero, filas, columnas FROM tablero WHERE idJuego=?", [(int)$id]);
    $tablero = mysqli_fetch_assoc($t);
    if (!$tablero) json_response(['error'=>'Tablero no encontrado'], 500);
    if ($x < 0 || $x >= (int)$tablero['filas'] || $y < 0 || $y >= (int)$tablero['columnas']) {
        json_response(['error'=>'Coordenadas fuera de rango'], 400);
    }

    // cargar casilla
    $c = q("SELECT * FROM casilla WHERE idTablero=? AND cordX=? AND cordY=?", [$tablero['idTablero'], $x, $y]);
    $casilla = mysqli_fetch_assoc($c);
    if (!$casilla) json_response(['error'=>'Casilla no existe'], 404);
    if ($casilla['estado'] === 'destapada') json_response(['error'=>'Casilla ya destapada'], 409);

    // héroe correspondiente
    $p = q("SELECT * FROM personaje WHERE idJuego=? AND tipo=?", [(int)$id, (int)$casilla['tipo']]);
    $pj = mysqli_fetch_assoc($p);

    // resolver intento
    $req = (int)$casilla['esfuerzo'];
    $poder = (int)$pj['poder_actual'];

    if (!$pj['vivo'] || $poder <= 0) {
        // sin poder -> fracaso_sin_poder
        q("UPDATE casilla SET estado='destapada', destapada_en=NOW() WHERE idCasilla=?", [$casilla['idCasilla']]);
        $idRonda = current_round_id((int)$id);
        q("INSERT INTO intento_prueba (idJuego, idRonda, idCasilla, idPersonaje, resultado, prob_aplicada, poder_requerido, poder_antes, poder_despues)
           VALUES (?,?,?,?, 'fracaso_sin_poder', 50, ?, ?, ?)",
           [(int)$id, $idRonda, (int)$casilla['idCasilla'], (int)$pj['idPersonaje'], $req, $poder, $poder]);
        set_losses($idRonda, false);
        $final = eval_game_state((int)$id, (int)$tablero['idTablero']);
        json_response([
            'movimiento'=>[
                'resultado'=>'fracaso_sin_poder',
                'casilla'=>['x'=>$x,'y'=>$y,'tipo'=>(int)$casilla['tipo'],'esfuerzo'=>$req]
            ],
            'estado'=>$final
        ]);
    }

    // probabilidad
    $prob = 50;
    if ($poder > $req) $prob = 90;
    elseif ($poder === $req) $prob = 70;

    $roll = random_int(1,100);
    $exito = ($roll <= $prob);

    $poder_despues = $poder;
    $vivo = (int)$pj['vivo'];

    if ($exito) {
        $poder_despues = max(0, $poder - $req);
    } else {
        $poder_despues = 0;
        $vivo = 0; // inactivo
    }

    // persistir cambios
    q("UPDATE casilla SET estado='destapada', destapada_en=NOW() WHERE idCasilla=?", [$casilla['idCasilla']]);
    q("UPDATE personaje SET poder_actual=?, vivo=? WHERE idPersonaje=?", [$poder_despues, $vivo, (int)$pj['idPersonaje']]);

    $idRonda = current_round_id((int)$id);
    q("INSERT INTO intento_prueba (idJuego, idRonda, idCasilla, idPersonaje, resultado, prob_aplicada, poder_requerido, poder_antes, poder_despues)
       VALUES (?,?,?,?, ?, ?, ?, ?, ?)",
       [(int)$id, $idRonda, (int)$casilla['idCasilla'], (int)$pj['idPersonaje'],
        $exito ? 'exito' : 'fracaso_intento', $prob, $req, $poder, $poder_despues]);

    set_losses($idRonda, $exito);

    $final = eval_game_state((int)$id, (int)$tablero['idTablero']);

    json_response([
        'movimiento'=>[
            'resultado'=>$exito ? 'exito' : 'fracaso_intento',
            'prob'=>$prob,
            'poder_antes'=>$poder,
            'poder_despues'=>$poder_despues
        ],
        'estado'=>$final
    ]);
}

function post_gamer_surrender($id) {
    $u = require_auth();
    $own = q("SELECT 1 FROM juego_usuario WHERE idJuego=? AND idUsuario=?", [(int)$id, $u['idUsuario']]);
    if (!mysqli_fetch_assoc($own)) json_response(['error'=>'No autorizado'], 403);

    q("UPDATE juego SET estado='perdido' WHERE idJuego=? AND estado IN ('creado','en_curso')", [(int)$id]);

    $t = q("SELECT idTablero FROM tablero WHERE idJuego=?", [(int)$id]);
    $idTablero = (int)(mysqli_fetch_assoc($t)['idTablero'] ?? 0);
    $cells = [];
    if ($idTablero) {
        $cRes = q("SELECT cordX, cordY, tipo, esfuerzo, estado FROM casilla WHERE idTablero=?", [$idTablero]);
        while ($r = mysqli_fetch_assoc($cRes)) $cells[] = $r;
    }
    $pcs = [];
    $pRes = q("SELECT nombre, tipo, poder_actual, vivo FROM personaje WHERE idJuego=?", [(int)$id]);
    while ($r = mysqli_fetch_assoc($pRes)) $pcs[] = $r;

    json_response(['estado'=>'perdido','casillas'=>$cells,'heroes'=>$pcs]);
}

// ------------------------------- Router ---------------------------------------

function route_request() {
    $p = rtrim(path(), '/');
    $m = effective_method();

    // AUTH
    if ($p === '/auth/register' && $m === 'POST') post_auth_register();
    if ($p === '/auth/login'    && $m === 'POST') post_auth_login();
    if ($p === '/auth/logout'   && $m === 'POST') post_auth_logout();

    // ADMIN
    if ($p === '/admin/users' && $m === 'GET')  get_admin_users();
    if ($p === '/admin/users' && $m === 'POST') post_admin_users();
    if (preg_match('#^/admin/users/(\d+)/role$#', $p, $mm) && $m === 'PATCH') patch_admin_user_role((int)$mm[1]);
    if (preg_match('#^/admin/users/(\d+)$#', $p, $mm) && $m === 'DELETE') delete_admin_user((int)$mm[1]);

    // USER
    if ($p === '/user/me'       && $m === 'GET')  get_user_me();
    if ($p === '/user/password' && $m === 'POST') post_user_password();
    if ($p === '/user/stats'    && $m === 'GET')  get_user_stats();

    // GAMER
    if ($p === '/gamer/games' && $m === 'POST') post_gamer_games();
    if (preg_match('#^/gamer/games/(\d+)$#', $p, $mm) && $m === 'GET')  get_gamer_game((int)$mm[1]);
    if (preg_match('#^/gamer/games/(\d+)/reveal$#', $p, $mm) && $m === 'POST') post_gamer_reveal((int)$mm[1]);
    if (preg_match('#^/gamer/games/(\d+)/surrender$#', $p, $mm) && $m === 'POST') post_gamer_surrender((int)$mm[1]);

    // 404
    json_response(['error'=>'Ruta no encontrada', 'method'=>$m, 'path'=>$p], 404);
}
