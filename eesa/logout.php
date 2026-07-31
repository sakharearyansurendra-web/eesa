<?php
require_once __DIR__ . '/config.php';
if (is_logged_in()) {
    audit($pdo, 'logout', current_user()['username']);
}
session_unset();
session_destroy();
redirect('/index.php');
