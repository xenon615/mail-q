<?php
require_once  dirname(__DIR__, 5) . '/wp-load.php';
$r = (new Mail_Q())->send_next(1, date('Y-m-d H:i:s', time()), false);


