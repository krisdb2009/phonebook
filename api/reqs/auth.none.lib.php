<?php
/*
Phone Book
----------
NONE Auth Library
----------
Dylan Bickerstaff
----------
Functions that authenticate and cache sessions.
*/
if(!isset($singlePointEntry)){http_response_code(403);exit;}

if(!file_exists('../data/conf/auth.none.cfg.php')) {
    file_put_contents('../data/conf/auth.none.cfg.php',
'<?php
/*
Phone Book
----------
NONE Auth Library Config
----------
Dylan Bickerstaff
----------
No auth configuration.
*/
if(!isset($singlePointEntry)){http_response_code(403);exit;}

$auth_none_everyone_is_admin = true;

?>'
    );
}
require_once('../data/conf/auth.none.cfg.php'); //Import config for NONE.
function auth_plugin_authenticated() { //Main function called by AUTH Lib.
    global $auth_none_everyone_is_admin;
    return $auth_none_everyone_is_admin;
}
?>