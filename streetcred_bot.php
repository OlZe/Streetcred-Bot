<?php

define("API_AUTH_TOKEN", file_get_contents("./auth_token"));
define("API_URL", "https://api.telegram.org/bot".API_AUTH_TOKEN."/");



function replyToMessage($chat_id, $message_id, $replyText) {
    $parameters = array(
        "chat_id" => $chat_id,
        "reply_to_message_id" => $message_id,
        "text" => $replyText,
        "method" => "sendMessage");
    header("Content-Type: application/json");
    echo json_encode($parameters);
}



$content = file_get_contents("php://input");
$update = json_decode($content, true);
$message = $update["message"];
replyToMessage($message["chat"]["id"], $message["message_id"], "hello world!")

?>