<?php

define("API_AUTH_TOKEN", file_get_contents("./auth_token"));
define("API_URL", "https://api.telegram.org/bot".API_AUTH_TOKEN."/");
define("GIVE_CRED_COMMAND", "respect");
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
    if(strpos($message["text"], GIVE_CRED_COMMAND) === 0) {
        if(messageIsReply($message)) {
            $replyRecieverName = $message["reply_to_message"]["from"]["first_name"];
            $replyRecieverId = $message["reply_to_message"]["from"]["id"];
            $replyRecieverCred = addCredToUser($message["chat"]["id"], $replyRecieverId, 1);
            replyToMessage($message["chat"]["id"], $message["message_id"], "Streetcred: ".$replyRecieverCred);
        }
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

function getCredForUser($chat_id, $user_id) {
    $cred = 0;
    $file = getSavedCred($chat_id);
    if(isset($file[$user_id])) {
        $cred = $file[$user_id];
    }
    return $cred;
}

function addCredToUser($chat_id, $user_id, $amount_cred) {
    $newCred = null;
    $file = getSavedCred($chat_id);
    if(isset($file[$user_id])) {
        $file[$user_id] += $amount_cred;
    }
    else {
        $file[$user_id] = $amount_cred;
    }
    $newCred = $file[$user_id];
    file_put_contents("./".$chat_id, json_encode($file));
    return $newCred;
}

function getSavedCred($chat_id) {
    $fileString = file_get_contents("./".$chat_id);
    $fileJson = null;
    if($fileString === false) {
        // If there is no file yet, make one
        $fileJson = array();
    }
    else {
        $fileJson = json_decode($fileString, true);
    }
    return $fileJson;
}


?>