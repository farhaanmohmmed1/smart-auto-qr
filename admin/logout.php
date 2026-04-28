<?php
require_once '../config/config.php';
session_unset();
session_destroy();
redirect(BASE_URL . '/admin/login.php');
