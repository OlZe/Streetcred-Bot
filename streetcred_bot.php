<?php

define("API_AUTH_TOKEN", file_get_contents("./auth_token"));
define("API_URL", "https://api.telegram.org/bot".API_AUTH_TOKEN."/");
define("GIVE_CRED_COMMAND", "respect");
define("GET_CRED_COMMAND", "/respect");
define("HELP_COMMAND", "/help");
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
        if($this->messageIsCommand($message["text"], GIVE_CRED_COMMAND)) {
            $answerText = $this->handleGiveCredCommand($message);
        }
        elseif($this->messageIsCommand($message["text"], GET_CRED_COMMAND)) {
            $answerText = $this->handleGetCredCommand($message);
        }
        elseif($this->messageIsCommand($message["text"], HELP_COMMAND)) {
            $answerText = $this->handleHelpCommand();
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
        $cred = $this->$dao->getTotalCredForUser($chatId, $userId); 
        return $userName." has ".$cred." streetcred.";
    }
        
    private function handleGiveCredCommand($message) {
        $text = null;
        if($this->messageIsReply($message)) {
            $chatId = $message["chat"]["id"];
            $recieverMessageId = $message["reply_to_message"]["message_id"];
            $recieverUserId = $message["reply_to_message"]["from"]["id"];
            $recieverName = $message["reply_to_message"]["from"]["first_name"];
            $recieverCredData = $this->$dao->getCredDataForUser($chatId, $recieverUserId);
            $donorUserId = $message["from"]["id"];
            $donorName = $message["from"]["first_name"];

            if(!isset($recieverCredData[$recieverMessageId])) {
                $recieverCredData[$recieverMessageId] = array();
            }
            $recieverCredData[$recieverMessageId]["messageText"] = $message["reply_to_message"]["text"];
            $recieverCredData[$recieverMessageId]["time"]  = $message["reply_to_message"]["date"];
            if(!isset($recieverCredData[$recieverMessageId]["credSources"])) {
                $recieverCredData[$recieverMessageId]["credSources"] = array();
            }
            if(!isset($recieverCredData[$recieverMessageId]["credSources"][$donorUserId])) {
                $recieverCredData[$recieverMessageId]["credSources"][$donorUserId] = array();
                $recieverCredData[$recieverMessageId]["credSources"][$donorUserId]["givenCredAmount"] = 0;
            }
            $addCredAmount = $this->getGiveCredAmount($message["text"]);
            $recieverCredData[$recieverMessageId]["credSources"][$donorUserId]["givenCredAmount"] += $addCredAmount;
            $recieverCredData[$recieverMessageId]["credSources"][$donorUserId]["firstName"] = $donorName;
            $this->$dao->saveCredDataForUser($chatId, $recieverUserId, $recieverCredData);
            $newRecieverCred = $recieverCredData[$recieverMessageId]["credSources"][$donorUserId]["givenCredAmount"];

            $plusSign = $addCredAmount >= 0 ? "+" : ""; // negative numbers already have a "-"-symbol in front
            $text = $plusSign.$addCredAmount." streetcred to ".$recieverName.": ".$newRecieverCred;
        }
        return $text;
    }

    private function handleHelpCommand() {
        return  "Use /respect to see how much streetcred you have.\n".
                "Reply to someone's message with 'respect x' to give them +x streetcred!";
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

    private function messageIsCommand($messageText, $command) {
        $messageText = strtolower($messageText);
        return strpos($messageText, $command) === 0;
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
    public function getTotalCredForUser($chatId, $userId) {
        $cred = 0;
        $userData = $this->getCredDataForUser($chatId, $userId);
        foreach($userData as $credRecievedMessageId => $credRecievedMessage) {
            foreach($credRecievedMessage["credSources"] as $userIdGivenCred => $userGivenCred) {
                $cred += $userGivenCred["givenCredAmount"];
            }
        }
        return $cred;
    }

    public function getCredDataForUser($chatId, $userId) {
        return $this->getCredData($chatId)[$userId];
    }

    public function saveCredDataForUser($chatId, $userId, $credData) {
        $allCredData = $this->getCredData($chatId);
        $allCredData[$userId] = $credData;
        $this->saveCredData($chatId, $allCredData);
    }
    
    private function getCredData($chatId) {
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

    private function saveCredData($chatId, $credData) {
        file_put_contents("./".$chatId, json_encode($credData));
    }
}

/*
Structure of saved data for each file (chat_id):
{
    "<userId">: {
        "<messageIdThatHeRecievedCredFor>": {
            "messageText": string,
            "botReplyMessageId": int,
            "date": int,
            "credSources": {
                "<userIdThatGaveHimCred>": {
                    "givenCredAmount": int,
                    "firstName": string
                },
                "<userIdThatGaveHimCred>": { ... },
                ...
            }
        },
        "<messageIdThatHeRecievedCredFor>": { ... },
        ...
    }
    "<userId">: { ... },
    ...
}


*/



?>