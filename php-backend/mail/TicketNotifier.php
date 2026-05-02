<?php

require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/../firestore-client.php';

class TicketNotifier {
    public static function sendTicketCreatedNotification($ticket) {
        $ownerId = $ticket['ownerId'] ?? $ticket['userId'] ?? $ticket['caller_id'] ?? null;
        $email = $ticket['email'] ?? $ticket['caller_email'] ?? null;

        $phone = null;
        $firestore = new FirestoreClient();

        if ($ownerId) {
            error_log("[DEBUG] LOOKUP USING USER ID: " . $ownerId);
            $doc = $firestore->getDocument('users', $ownerId);
            if ($doc && isset($doc['fields']['phone']['stringValue'])) {
                $phone = $doc['fields']['phone']['stringValue'];
            } elseif ($doc && isset($doc['phone'])) {
                $phone = $doc['phone'];
            }
        } 
        
        if (!$phone && $email) {
            error_log("[DEBUG] LOOKUP USING EMAIL QUERY: " . $email);
            $results = $firestore->runQuery([
                "from" => [["collectionId" => "users"]],
                "where" => [
                    "fieldFilter" => [
                        "field" => ["fieldPath" => "email"],
                        "op" => "EQUAL",
                        "value" => ["stringValue" => $email]
                    ]
                ]
            ]);

            error_log("[DEBUG] FIRESTORE QUERY RESULT: " . json_encode($results));

            foreach ($results as $row) {
                if (isset($row['document']['fields']['phone']['stringValue'])) {
                    $phone = $row['document']['fields']['phone']['stringValue'];
                    break;
                } elseif (isset($row['phone'])) {
                    $phone = $row['phone'];
                    break;
                }
            }
        }

        if ($phone) {
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (substr($phone, 0, 2) !== '91') {
                $phone = '91' . $phone;
            }
            $phone = '+' . $phone;
        }

        error_log("[DEBUG] FINAL PHONE: " . ($phone ?? 'NULL'));

        if ($phone) {
            $to = "whatsapp:" . $phone;
            $message = "🎫 *New Ticket Created*\nID: " . ($ticket['ticket_number'] ?? $ticket['id'] ?? '') . "\nTitle: " . ($ticket['short_description'] ?? $ticket['title'] ?? '');
            NotificationService::sendWhatsApp($to, $message);
        } else {
            error_log("[TicketNotifier] No phone found → skipping WhatsApp");
        }
    }
}
