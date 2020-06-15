<?php

define("API_AUTH_TOKEN", file_get_contents("./auth_token"));
define("API_URL", "https://api.telegram.org/bot".API_AUTH_TOKEN."/");



?>