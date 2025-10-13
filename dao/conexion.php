<?php

$conexion = mysqli_connect('localhost', 'root', '', 'hobbyte');
if (!$conexion) {
    print "Fallo al conectar a MySQL: " . mysqli_connect_error();
}