<?php
// RacconMail - Root Controller
// Clears any legacy Roundcube session cookie to prevent old webmail from ever rendering
if (isset($_COOKIE['roundcube_sessid'])) {
    setcookie('roundcube_sessid', '', time() - 3600, '/');
}

// Always render the official RacconMail Landing Page
if (file_exists(__DIR__ . '/index.html')) {
    include __DIR__ . '/index.html';
    exit;
}
echo "Welcome to RacconMail";
