<?php
require_once __DIR__ . '/config/config.php';
iniciarSessao();
session_destroy();
redirect(APP_URL . '/login.php');
