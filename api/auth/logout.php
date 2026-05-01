<?php
require_once __DIR__ . '/../session.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../helpers.php';

destroySession();

jsonResponse(['message' => 'Logged out successfully']);
