
CREATE TABLE juego (
    idJuego       INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(80) NOT NULL,
    estado        ENUM('creado','en_curso','ganado','perdido') NOT NULL DEFAULT 'creado',
);

CREATE TABLE tablero (
    idTablero INT AUTO_INCREMENT PRIMARY KEY,
    idJuego   INT NOT NULL,
    filas     INT NOT NULL DEFAULT 4,    
    columnas  INT NOT NULL DEFAULT 5,
    UNIQUE KEY uk_tablero_juego (idJuego),
    CONSTRAINT fk_tablero_juego
        FOREIGN KEY (idJuego) REFERENCES juego(idJuego)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE casilla (
    idCasilla INT AUTO_INCREMENT PRIMARY KEY,
    idTablero INT NOT NULL,
    cordX     INT NOT NULL,
    cordY     INT NOT NULL,
    -- 1 -> magia, 2 -> fuerza, 3 -> habilidad
    tipo      TINYINT NOT NULL CHECK (tipo IN (1,2,3)),
    esfuerzo  INT NOT NULL CHECK (esfuerzo IN (5,10,15,20,25,30,35,40,45,50)),
    estado    ENUM('oculta','destapada') NOT NULL DEFAULT 'oculta',
    destapada_en DATETIME NULL,
    UNIQUE KEY uk_casilla_coord (idTablero, cordX, cordY),
    KEY idx_casilla_tablero (idTablero),
    CONSTRAINT fk_casilla_tablero
        FOREIGN KEY (idTablero) REFERENCES tablero(idTablero)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE personaje (
    idPersonaje   INT AUTO_INCREMENT PRIMARY KEY,
    idJuego       INT NOT NULL,
    nombre        VARCHAR(50) NOT NULL,
    tipo          TINYINT NOT NULL CHECK (tipo IN (1,2,3)),
    poder_max     INT NOT NULL CHECK (poder_max = 50),
    poder_actual  INT NOT NULL CHECK (poder_actual BETWEEN 0 AND 50),
    vivo          BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE KEY uk_personaje_nombre_juego (idJuego, tipo),
    KEY idx_personaje_juego (idJuego),
    CONSTRAINT fk_personaje_juego
        FOREIGN KEY (idJuego) REFERENCES juego(idJuego)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE ronda (
    idRonda   INT AUTO_INCREMENT PRIMARY KEY,
    idJuego   INT NOT NULL,
    numero    INT NOT NULL,
    perdidas_consecutivas INT NOT NULL DEFAULT 0,
    UNIQUE KEY uk_ronda_juego_num (idJuego, numero),
    CONSTRAINT fk_ronda_juego
        FOREIGN KEY (idJuego) REFERENCES juego(idJuego)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE intento_prueba (
    idIntento     BIGINT AUTO_INCREMENT PRIMARY KEY,
    idJuego       INT NOT NULL,
    idRonda       INT NOT NULL,
    idCasilla     INT NOT NULL,
    idPersonaje   INT NOT NULL,
    resultado     ENUM('exito','fracaso_sin_poder','fracaso_intento') NOT NULL,
    prob_aplicada TINYINT NOT NULL CHECK (prob_aplicada IN (50,70,90)),
    poder_requerido INT NOT NULL CHECK (poder_requerido IN (5,10,15,20,25,30,35,40,45,50)),
    poder_antes   INT NOT NULL CHECK (poder_antes BETWEEN 0 AND 50),
    poder_despues INT NOT NULL CHECK (poder_despues BETWEEN 0 AND 50),
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_intento_juego (idJuego),
    KEY idx_intento_ronda (idRonda),
    KEY idx_intento_casilla (idCasilla),

    CONSTRAINT fk_intento_juego
        FOREIGN KEY (idJuego) REFERENCES juego(idJuego)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_intento_ronda
        FOREIGN KEY (idRonda) REFERENCES ronda(idRonda)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_intento_casilla
        FOREIGN KEY (idCasilla) REFERENCES casilla(idCasilla)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_intento_personaje
        FOREIGN KEY (idPersonaje) REFERENCES personaje(idPersonaje)
        ON DELETE CASCADE ON UPDATE CASCADE
);




CREATE TABLE juego (
    idJuego       INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(80) NOT NULL,
    estado        ENUM('creado','en_curso','ganado','perdido') NOT NULL DEFAULT 'creado'
);

CREATE TABLE tablero (
    idTablero INT AUTO_INCREMENT PRIMARY KEY,
    idJuego   INT NOT NULL,
    filas     INT NOT NULL DEFAULT 4,    
    columnas  INT NOT NULL DEFAULT 5,
    UNIQUE KEY uk_tablero_juego (idJuego),
    CONSTRAINT fk_tablero_juego
        FOREIGN KEY (idJuego) REFERENCES juego(idJuego)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE casilla (
    idCasilla INT AUTO_INCREMENT PRIMARY KEY,
    idTablero INT NOT NULL,
    cordX     INT NOT NULL,
    cordY     INT NOT NULL,
    -- 1 -> magia, 2 -> fuerza, 3 -> habilidad
    tipo      TINYINT NOT NULL CHECK (tipo IN (1,2,3)),
    esfuerzo  INT NOT NULL CHECK (esfuerzo IN (5,10,15,20,25,30,35,40,45,50)),
    estado    ENUM('oculta','destapada') NOT NULL DEFAULT 'oculta',
    destapada_en DATETIME NULL,
    UNIQUE KEY uk_casilla_coord (idTablero, cordX, cordY),
    KEY idx_casilla_tablero (idTablero),
    CONSTRAINT fk_casilla_tablero
        FOREIGN KEY (idTablero) REFERENCES tablero(idTablero)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE personaje (
    idPersonaje   INT AUTO_INCREMENT PRIMARY KEY,
    idJuego       INT NOT NULL,
    nombre        VARCHAR(50) NOT NULL,
    tipo          TINYINT NOT NULL CHECK (tipo IN (1,2,3)),
    poder_max     INT NOT NULL CHECK (poder_max = 50),
    poder_actual  INT NOT NULL CHECK (poder_actual BETWEEN 0 AND 50),
    vivo          BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE KEY uk_personaje_nombre_juego (idJuego, tipo),
    KEY idx_personaje_juego (idJuego),
    CONSTRAINT fk_personaje_juego
        FOREIGN KEY (idJuego) REFERENCES juego(idJuego)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE ronda (
    idRonda   INT AUTO_INCREMENT PRIMARY KEY,
    idJuego   INT NOT NULL,
    numero    INT NOT NULL,
    perdidas_consecutivas INT NOT NULL DEFAULT 0,
    UNIQUE KEY uk_ronda_juego_num (idJuego, numero),
    CONSTRAINT fk_ronda_juego
        FOREIGN KEY (idJuego) REFERENCES juego(idJuego)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE intento_prueba (
    idIntento     BIGINT AUTO_INCREMENT PRIMARY KEY,
    idJuego       INT NOT NULL,
    idRonda       INT NOT NULL,
    idCasilla     INT NOT NULL,
    idPersonaje   INT NOT NULL,
    resultado     ENUM('exito','fracaso_sin_poder','fracaso_intento') NOT NULL,
    prob_aplicada TINYINT NOT NULL CHECK (prob_aplicada IN (50,70,90)),
    poder_requerido INT NOT NULL CHECK (poder_requerido IN (5,10,15,20,25,30,35,40,45,50)),
    poder_antes   INT NOT NULL CHECK (poder_antes BETWEEN 0 AND 50),
    poder_despues INT NOT NULL CHECK (poder_despues BETWEEN 0 AND 50),
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_intento_juego (idJuego),
    KEY idx_intento_ronda (idRonda),
    KEY idx_intento_casilla (idCasilla),

    CONSTRAINT fk_intento_juego
        FOREIGN KEY (idJuego) REFERENCES juego(idJuego)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_intento_ronda
        FOREIGN KEY (idRonda) REFERENCES ronda(idRonda)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_intento_casilla
        FOREIGN KEY (idCasilla) REFERENCES casilla(idCasilla)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_intento_personaje
        FOREIGN KEY (idPersonaje) REFERENCES personaje(idPersonaje)
        ON DELETE CASCADE ON UPDATE CASCADE
);
