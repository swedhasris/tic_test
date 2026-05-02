<?php

require_once __DIR__ . '/../vendor/autoload.php';
use Twilio\Rest\Client;
use Twilio\Http\CurlClient;

class NotificationService {
    public static function sendWhatsApp($to, $messageBody) {
        // Use real credentials from previous conversation context if available
        $sid = "AC16ea3e18ffd7a8d951dd27f253b948f8";
        $token = "d76967658c4381fc173ac7b41f70f28c";
        $from = "whatsapp:+14155238886";

        error_log("[DEBUG] FINAL WHATSAPP TO: " . $to);

        try {
            // Disable SSL for local dev
            $options = [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ];
            $httpClient = new CurlClient($options);
            
            $client = new Client($sid, $token, null, null, $httpClient);
            $message = $client->messages->create(
                $to,
                [
                    "from" => $from,
                    "body" => $messageBody
                ]
            );

            error_log("[TWILIO] SID: " . $message->sid);
            error_log("[TWILIO] STATUS: " . $message->status);

            return $message;
        } catch (Exception $e) {
            error_log("[TWILIO] ERROR: " . $e->getMessage());
            return null;
        }
    }
}
