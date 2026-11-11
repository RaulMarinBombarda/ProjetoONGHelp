<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

destruirSessao();

header('Location: login.php?saiu=1');
exit;
