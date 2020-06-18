<?php

define("API_AUTH_TOKEN", file_get_contents("./auth_token"));
define("API_URL", "https://api.telegram.org/bot".API_AUTH_TOKEN."/");
define("GIVE_CRED_COMMAND", "respect");
(new Controller())->handleWebRequest();

class Controller {
    private $service;

    public function __construct() {
        $this->$service = new Service();
    }

    public function handleWebRequest()  {
        $request = json_decode(file_get_contents("php://input"), true);
        $answer = null;
        if($this->isTextMessage($request)) {
            $answer = $this->$service->handleTextMessage($request["message"]);
        }
        else {
            $answer = "unsupported request";
        }
        $this->respondWebRequest($answer);
    }
    
    private function respondWebRequest($body) {
        header("Content-Type: application/json");
        echo(json_encode($body));
    }
    
    private function isTextMessage($request) {
        return isset($request["message"]) && isset($request["message"]["text"]);
    }
}
    
class Service {
    private $dao;
    
    public function __construct() {
        $this->$dao = new Dao();
    }

    public function handleTextMessage($message) {
        $answer = null;
        if(strpos($message["text"], GIVE_CRED_COMMAND) === 0) {
            $replyText = $this->handleGiveCredCommand($message);
            if($replyText != null) {
                $answer = $this->prepareReplyToMessage($message, $replyText);
            }
        }
        return $answer;
    }
        
    private function handleGiveCredCommand($message) {
        $text = null;
        if($this->messageIsReply($message)) {
            $replyRecieverName = $message["reply_to_message"]["from"]["first_name"];
            $replyRecieverId = $message["reply_to_message"]["from"]["id"];
            $replyRecieverCred = $this->$dao->addCredToUser($message["chat"]["id"], $replyRecieverId, 1);
            $text = "Streetcred: ".$replyRecieverCred;
        }
        return $text;
    }

    private function messageIsReply($message) {
        return isset($message["reply_to_message"]);
    }

    private function prepareReplyToMessage($message, $text) {
        return array(
            "chat_id" => $message["chat"]["id"],
            "reply_to_message_id" => $message["message_id"],
            "text" => $text,
            "method" => "sendMessage");
    }
}

class Dao {
    public function getCredForUser($chat_id, $user_id) {
        $cred = 0;
        $file = $this->getCredForChat($chat_id);
        if(isset($file[$user_id])) {
            $cred = $file[$user_id];
        }
        return $cred;
    }
    
    public function addCredToUser($chat_id, $user_id, $amount_cred) {
        $chatCred = $this->getCredForChat($chat_id);
        if(isset($chatCred[$user_id])) {
            $chatCred[$user_id] += $amount_cred;
        }
        else {
            $chatCred[$user_id] = $amount_cred;
        }
        $newUserCred = $chatCred[$user_id];
        $this->saveCredForChat($chat_id, $chatCred);
        return $newUserCred;
    }
    
    private function getCredForChat($chat_id) {
        $fileString = file_get_contents("./".$chat_id);
        $fileJson = null;
        if($fileString === false) {
            // If there is no file yet, return empty array
            $fileJson = array();
        }
        else {
            $fileJson = json_decode($fileString, true);
        }
        return $fileJson;
    }
    
    private function saveCredForChat($chat_id, $credData) {
        file_put_contents("./".$chat_id, json_encode($credData));
    }
}




?>