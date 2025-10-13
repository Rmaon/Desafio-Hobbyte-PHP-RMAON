<?php

namespace services;

use config\Config;

class Database
{
    /** @var \mysqli|null */
    private static $connection = null;

    public static function connect()
    {
        if (self::$connection instanceof \mysqli) {
            return self::$connection;
        }

        $conexion = mysqli_connect('127.0.0.1', 'root', '', 'hobbyte');
        if (!$conexion) {
            throw new \RuntimeException('Fallo al conectar a MySQL: ' . mysqli_connect_error());
        }
        mysqli_set_charset($conexion, 'utf8mb4');
        self::$connection = $conexion;

        return self::$connection;
    }
}
