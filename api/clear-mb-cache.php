<?php
session_start();
require_once dirname(__DIR__) . '/spotify.php';

if (!is_authenticated()) {
    http_response_code(403);
    exit('Not authenticated');
}

get_db()->exec("DELETE FROM mb_cache");
echo "mb_cache cleared — " . date('Y-m-d H:i:s');
