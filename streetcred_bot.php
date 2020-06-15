<?php

define("API_AUTH_TOKEN", file_get_contents("./auth_token"));
define("API_URL", "https://api.telegram.org/bot".API_AUTH_TOKEN."/");
handleWebRequest();


function handleWebRequest()  {
    $request = json_decode(file_get_contents("php://input"), true);
    if(isTextMessage($request)) {
        handleTextMessage($request["message"]);
    }
    else {
        echo("unsupported request");
    }
}

function handleTextMessage($message) {
    if(messageIsReply($message)) {
        replyToMessage($message["chat"]["id"], $message["message_id"], "yo");
    }
}

function replyToMessage($chat_id, $message_id, $replyText) {
    $parameters = array(
        "chat_id" => $chat_id,
        "reply_to_message_id" => $message_id,
        "text" => $replyText,
        "method" => "sendMessage");
    respondWebRequest($parameters);
}

function isTextMessage($request) {
    return isset($request["message"]) && isset($request["message"]["text"]);
}

function messageIsReply($message) {
    return isset($message["reply_to_message"]);
}

function respondWebRequest($body) {
    header("Content-Type: application/json");
    echo(json_encode($body));
}


?>