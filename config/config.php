<?php

namespace services;

use config\Config;

class Database
{
    public static function connect()
    {
        $conexion = mysqli_connect('localhost', 'root', '', 'hobbyte');
        if (!$conexion) {
            print "Fallo al conectar a MySQL: " . mysqli_connect_error();
        }
        mysqli_set_charset($conexion, 'utf8mb4');
        return $conexion;
    }
}
