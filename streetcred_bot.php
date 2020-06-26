<?php

define("API_AUTH_TOKEN", file_get_contents("./auth_token"));
define("API_URL", "https://api.telegram.org/bot".API_AUTH_TOKEN."/");
define("API_METHOD_SEND_MESSAGE", "sendMessage");
define("API_METHOD_EDIT_MESSAGE", "editMessageText");
define("GIVE_CRED_COMMAND", "respect");
define("GET_CRED_COMMAND", "/respect");
define("HELP_COMMAND", "/help");
(new Controller())->handleWebRequest();

class Controller {
    private $service;

    public function __construct() {
        $this->service = new Service();
    }

    public function handleWebRequest()  {
        $request = json_decode(file_get_contents("php://input"), true);
        if($this->isTextMessage($request)) {
            $responeRequest = $this->service->handleTextMessage($request["message"]);
            if(isset($responeRequest)) {
                $this->sendRequest($responeRequest);
            }
        }
        else {
            echo('unsupported request');
        }
    }
    
    private function sendRequest($outgoingRequest) {
        $curl = curl_init(API_URL.$outgoingRequest->method);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($outgoingRequest->body));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type:application/json"));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = json_decode(curl_exec($curl));
        curl_close($curl);
        if(is_callable($outgoingRequest->callbackFn)) {
            $outgoingRequest->callbackFn($result);
        }
    }
    
    private function isTextMessage($request) {
        return isset($request["message"]) && isset($request["message"]["text"]);
    }
}

class OutgoingRequest {
    public $method;
    public $body;
    public $callbackFn;
}
    
class Service {
    private $dao;
    
    public function __construct() {
        $this->dao = new Dao();
    }

    public function handleTextMessage($message) {
        $responseRequest = null;
        if($this->messageIsCommand($message["text"], GIVE_CRED_COMMAND)) {
            $responseRequest = $this->handleGiveCredCommand($message);
        }
        elseif($this->messageIsCommand($message["text"], GET_CRED_COMMAND)) {
            $responseRequest = $this->handleGetCredCommand($message);
        }
        elseif($this->messageIsCommand($message["text"], HELP_COMMAND)) {
            $responseRequest = $this->handleHelpCommand($message);
        }
        return $responseRequest;
    }

    private function handleGetCredCommand($message) {
        $userId = $message["from"]["id"];
        $userName = $message["from"]["first_name"];
        $chatId = $message["chat"]["id"];
        $cred = $this->dao->getTotalCredForUser($chatId, $userId);

        $answerText = $userName." has ".$cred." streetcred.";
        return $this->prepareReplyToMessage($message, $answerText, null);
    }
        
    private function handleGiveCredCommand($message) {
        $responseRequest = null;
        if($this->messageIsReply($message)) {
            $chatId = $message["chat"]["id"];
            $recieverMessageId = $message["reply_to_message"]["message_id"];
            $recieverUserId = $message["reply_to_message"]["from"]["id"];
            $recieverName = $message["reply_to_message"]["from"]["first_name"];
            $recieverCredData = $this->dao->getCredDataForUser($chatId, $recieverUserId);
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
            $this->dao->saveCredDataForUser($chatId, $recieverUserId, $recieverCredData);
            $newRecieverCred = $this->dao->getTotalCredForUser($chatId, $recieverUserId);

            $plusSign = $addCredAmount >= 0 ? "+" : ""; // negative numbers already have a "-"-symbol in front
            $answerText = $plusSign.$addCredAmount." streetcred to ".$recieverName.": ".$newRecieverCred;
            $responseRequest = $this->prepareReplyToMessage($message["reply_to_message"], $answerText, $callbackFn);
        }
        return $responseRequest;
    }



    private function handleHelpCommand($message) {
        $answerText =   "Use /respect to see how much streetcred you have.\n".
                        "Reply to someone's message with 'respect x' to give them +x streetcred!";
        return $this->prepareReplyToMessage($message, $answerText, null);
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

    private function prepareReplyToMessage($message, $text, $callbackFn) {
        $request = new OutgoingRequest();
        $request->method = API_METHOD_SEND_MESSAGE;
        $request->body = array(
            "chat_id" => $message["chat"]["id"],
            "reply_to_message_id" => $message["message_id"],
            "text" => $text);
        // $request->callbackFn = $callbackFn;
        return $request;
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