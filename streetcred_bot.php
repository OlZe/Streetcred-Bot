<?php

define("API_AUTH_TOKEN", file_get_contents("./auth_token"));
define("API_URL", "https://api.telegram.org/bot".API_AUTH_TOKEN."/");
define("GIVE_CRED_COMMAND", "respect");
define("GET_CRED_COMMAND", "/respect");
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
        $answerObject = null;
        $answerText = null;
        if(strpos($message["text"], GIVE_CRED_COMMAND) === 0) {
            $answerText = $this->handleGiveCredCommand($message);
        }
        elseif(strpos($message["text"], GET_CRED_COMMAND) === 0) {
            $answerText = $this->handleGetCredCommand($message);
        }

        if($answerText != null) {
            $answerObject = $this->prepareReplyToMessage($message, $answerText);
        }
        return $answerObject;
    }

    private function handleGetCredCommand($message) {
        $userId = $message["from"]["id"];
        $userName = $message["from"]["first_name"];
        $chatId = $message["chat"]["id"];
        $cred = $this->$dao->getCredForUser($chatId, $userId); 
        return $userName." has ".$cred." streetcred.";
    }
        
    private function handleGiveCredCommand($message) {
        $text = null;
        if($this->messageIsReply($message)) {
            $credRecieverName = $message["reply_to_message"]["from"]["first_name"];
            $credRecieverId = $message["reply_to_message"]["from"]["id"];
            $addCredAmount = $this->getGiveCredAmount($message["text"]);
            $newCred = $this->$dao->addCredToUser($message["chat"]["id"], $credRecieverId, $addCredAmount);
            $text = $credRecieverName."'s streetcred: ".$newCred;
        }
        return $text;
    }

    private function getGiveCredAmount($text) {
        $amount = 1;
        // trim command and whitespaces away
        $textedAmount = trim(substr($text, strlen(GIVE_CRED_COMMAND)));
        if(strlen($textedAmount) > 0) {
            $parsedAmount = intval($textedAmount);
            if($parsedAmount != 0) {
                $amount = $parsedAmount;
            }
        }
        return $amount;
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
    public function getCredForUser($chatId, $userId) {
        $cred = 0;
        $file = $this->getCredForChat($chatId);
        if(isset($file[$userId])) {
            $cred = $file[$userId];
        }
        return $cred;
    }
    
    public function addCredToUser($chatId, $userId, $amountCred) {
        $chatCred = $this->getCredForChat($chatId);
        if(isset($chatCred[$userId])) {
            $chatCred[$userId] += $amountCred;
        }
        else {
            $chatCred[$userId] = $amountCred;
        }
        $newUserCred = $chatCred[$userId];
        $this->saveCredForChat($chatId, $chatCred);
        return $newUserCred;
    }
    
    private function getCredForChat($chatId) {
        $fileString = file_get_contents("./".$chatId);
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
    
    private function saveCredForChat($chatId, $credData) {
        file_put_contents("./".$chatId, json_encode($credData));
    }
}




?>