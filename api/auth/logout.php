<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../helpers.php';

$_SESSION = [];
session_destroy();

jsonResponse(['message' => 'Logged out successfully']);
