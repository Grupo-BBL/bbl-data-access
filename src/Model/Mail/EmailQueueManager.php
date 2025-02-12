<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailQueueManager extends DataAccess
{
    private $userName;
    private $password;
    private $smtpPort;
    private $hostAddress;
    private $sendFrom;
    private $debugLevel;

    public function __construct($p_db, $options = [])
    {
        parent::__construct($p_db, $options);

        $this->sendFrom = $options['sendFrom'] ?? null;
    }

    public function register()
    {
        global $_GLOBALS;

        $this->userName = $_GLOBALS["EMAIL_QUEUE_USER"] ?? null;
        $this->password = $_GLOBALS["EMAIL_QUEUE_PASSWORD"] ?? null;
        $this->smtpPort = $_GLOBALS["EMAIL_QUEUE_PORT"] ?? 465;
        $this->hostAddress = $_GLOBALS["EMAIL_QUEUE_SMTP_HOST"] ?? null;
        $this->sendFrom = $_GLOBALS["EMAIL_QUEUE_SEND_FROM"] ?? null;
        $this->debugLevel = $_GLOBALS["EMAIL_QUEUE_DEBUG_LEVEL"] ?? 0;

        if (!$this->userName) {
            die("DIE w/error: Starting email queue without username");
        }

        if (!$this->password) {
            die("DIE w/error: Starting email queue without password");
        }

        if (!$this->hostAddress) {
            die("NO host address for Email Queue Manager");
        }

        $this->debugLevel = SMTP::DEBUG_SERVER; //  = client and server messages

        $columns = [
            new GTKColumnMapping($this, "email_id", [
                "columnType" => "INTEGER",
                "isPrimaryKey" => true,
                "isAutoIncrement" => true,
                "dbKey" => "EmailID",
                "formLabel" => "ID de Correo Electrónico",
            ]),
            new GTKColumnMapping($this, "sender_email", ["dbKey" => "SenderEmail", "formLabel" => "Correo Electrónico del Remitente"]),
            new GTKColumnMapping($this, "subject", ["dbKey" => "Subject", "formLabel" => "Asunto"]),
            new GTKColumnMapping($this, "message_text", ["dbKey" => "MessageText", "formLabel" => "Texto del Mensaje"]),
            new GTKColumnMapping($this, "status", ["dbKey" => "Status", "formLabel" => "Estado"]),
            new GTKColumnMapping($this, "created_at", ["dbKey" => "CreatedAt", "formLabel" => "Fecha de Creación"]),
            new GTKColumnMapping($this, "sent_at", ["dbKey" => "SentAt", "formLabel" => "Fecha de Envío"]),
            new GTKColumnMapping($this, "send_at", ["dbKey" => "SendAt", "formLabel" => "Fecha de Programación de Envío"]),
            new GTKColumnMapping($this, "error_description", ["dbKey" => "ErrorDescription", "formLabel" => "Descripción del Error"]),
            new GTKColumnMapping($this, "recipient_email", ["dbKey" => "RecipientEmail", "formLabel" => "Correo Electrónico del Destinatario"]),
            new GTKColumnMapping($this, "cc_recipients", ["dbKey" => "CCRecipients", "formLabel" => "Destinatarios en Copia"]),
            new GTKColumnMapping($this, "bcc_recipients", ["dbKey" => "BCCRecipients", "formLabel" => "Destinatarios en Copia Oculta"]),
            new GTKColumnMapping($this, "is_html"),
            new GTKColumnMapping($this, "string_to_attach_filename"),
            new GTKColumnMapping($this, "string_to_attach"),
        ];

        $this->dataMapping = new GTKDataSetMapping($this, $columns);
        $this->defaultOrderByColumnKey = "CreatedAt";
        $this->defaultOrderByOrder = "DESC";
        $this->singleItemName = "Email";
        $this->pluralItemName = "Emails";
        $this->_allowsCreation = false;
    }

    function verifyEmailDomain($email)
    {
        $domain = substr(strrchr($email, "@"), 1);
        return checkdnsrr($domain, "MX");
    }

    function verifyEmailString($toCheck)
    {
        $emails = str_contains($toCheck, ",") ? explode(",", $toCheck) : [$toCheck];
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
        }
        return true;
    }

    public function addAlertToQueue($sendTo, $subject, $messageText, $options = [])
    {
        return $this->addToQueue($sendTo, $subject, $messageText, $options);
    }

    public function addDictionaryToQueue($dictionary)
    {
        $sendTo = $dictionary["to"] ?? $dictionary['sendTo'];
        $subject = $dictionary['subject'];
        $messageText = $dictionary['body'];

        return $this->addToQueue($sendTo, $subject, $messageText, $dictionary);
    }

    public function addToQueue($sendTo, $subject, $messageText, $options = [])
    {
        if (!$sendTo) {
            throw new Exception("El campo `sendTo` es obligatorio.");
        }

        if (is_string($sendTo)) {
            $sendTo = strtolower($sendTo);
            if (!$this->verifyEmailString($sendTo)) {
                throw new Exception("El campo `sendTo` no es un correo electrónico válido: " . print_r($sendTo, true));
            }
        } else if (is_array($sendTo)) {
            foreach ($sendTo as $email) {
                if (!$this->verifyEmailString($email)) {
                    throw new Exception("El campo `sendTo` no es un correo electrónico válido: " . print_r($sendTo, true));
                }
            }
            $sendTo = implode(",", $sendTo);
        }

        $toInsert = [
            "sender_email" => $options['senderEmail'] ?? $this->sendFrom,
            "subject" => $subject,
            "message_text" => $messageText,
            "status" => "Pending",
            "created_at" => date('Y-m-d H:i:s'),
            "sent_at" => null,
            "send_at" => $options['sendAt'] ?? null,
            "recipient_email" => strtolower($sendTo),
            "cc_recipients" => $options['ccRecipients'] ?? null,
            "bcc_recipients" => $options['bccRecipients'] ?? null,
            "is_html" => $options["isHTML"] ?? true,
            "string_to_attach" => $options["stringToAttach"] ?? null,
            "string_to_attach_filename" => $options["stringToAttachFilename"] ?? null,
        ];

        $this->insert($toInsert);
    }

    public function getPendingEmails($debug, $logFunction = null)
    {
        if ($debug && !$logFunction) {
            $logFunction = function ($arg) {
                error_log($arg);
            };
        }

        $currentTimestamp = date('Y-m-d H:i:s');

        if ($debug) {
            $logFunction("Will prepare query.");
        }

        $statement = $this->getDB()->prepare("
            SELECT *
            FROM EmailQueue
            WHERE (Status = 'Pending' OR Status IS NULL) AND (SendAt IS NULL OR SendAt <= ?)
        ");

        if ($debug) {
            $logFunction("Will execute query.");
        }

        $statement->execute([$currentTimestamp]);

        if ($debug) {
            $logFunction("Will fetchAll.");
        }

        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($debug) {
            $logFunction("Result for `getPendingEmails`: " . count($result));
        }

        return $result;
    }

    public function updateEmailWithSuccess($email, $currentTimestamp = null)
    {
        $timestamp = $currentTimestamp ?? date('Y-m-d H:i:s');

        $updateStmt = $this->getDB()->prepare("
            UPDATE EmailQueue
            SET Status = 'Sent', 
                SentAt = ?
            WHERE EmailID = ?
        ");
        $updateStmt->execute([$timestamp, $email['EmailID']]);
    }

    function sendEmail($mailer, $email)
    {
        $debug = false;

        $successString = "Sent email `";

        $mailer->Subject = $email['Subject'];
        $mailer->Body = $email['MessageText'];

        $successString .= $mailer->Subject . "` to... ";

        $recipientEmails = parseCSVLine($email['RecipientEmail']);
        $ccRecipients = parseCSVLine($email['CCRecipients']);
        $bccRecipients = parseCSVLine($email['BCCRecipients']);

        foreach ($recipientEmails as $address) {
            $successString .= $address . " ";
            $mailer->addAddress($address);
        }

        foreach ($ccRecipients as $address) {
            $mailer->addCC($address);
        }

        foreach ($bccRecipients as $address) {
            $mailer->addBCC($address);
        }

        if ($email["string_to_attach"]) {
            $mailer->addStringAttachment(
                $email["string_to_attach"],
                $email["string_to_attach_filename"] ?? "attachment.txt"
            );
        }

        try {
            $mailer->send();
            $this->updateEmailWithSuccess($email);

            if ($debug) {
                error_log($successString);
            }

            return $successString;
        } catch (Exception $e) {
            $this->updateEmailWithException($email, $e);

            $errorString = "FAIL @ Email Send Message: " . $e->getMessage();

            if ($debug) {
                error_log($errorString);
            }

            return $errorString;
        }
    }

    function sendPendingEmails($debug, $logFunction = null, $sendFrom = null)
    {
        if ($debug && !$logFunction) {
            $logFunction = function ($arg) {
                error_log($arg);
            };
        }

        if ($debug) {
            $logFunction("Will query for pending emails");
        }

        $pendingEmails = $this->getPendingEmails($debug, $logFunction);

        if ($debug) {
            $logFunction("Got pending emails: " . count($pendingEmails));
        }

        $mailer = $this->getMailer($sendFrom);

        $infoString = "";

        foreach ($pendingEmails as $email) {
            if (isTruthy($email["is_html"])) {
                $mailer->isHTML(true);
            }

            $toAppend = $this->sendEmail($mailer, $email) . "\n\n";

            if ($debug) {
                $logFunction($toAppend);
            }

            $infoString .= $toAppend;

            $mailer->clearAddresses();
            $mailer->clearCCs();
            $mailer->clearBCCs();
            $mailer->clearAttachments();
        }

        return $infoString;
    }

    public function reportError($subject, $body)
    {
        global $_GLOBALS;
        $errorEmail = $_GLOBALS["ON_ERROR_EMAIL"];

        if (!$errorEmail) {
            $message = "Aviso - Ha ocurrido un error en el sistema. Aun no se ha configurado un correo electrónico para recibir notificaciones de error. Favor tomar las acciones de lugar.";
            error_log($message);
            die($message);
        }

        DataAccessManager::get("email_queue")->addToQueue($errorEmail, $subject, $body);
    }

    function getMailer($sendFrom = null)
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->SMTPDebug = $this->debugLevel;

        $networkSupportsIPv6 = true;

        if ($networkSupportsIPv6) {
            $mail->Host = $this->hostAddress;
        } else {
            $mail->Host = gethostbyname($this->hostAddress);
        }

        $useImplicitTLS = true;

        if ($useImplicitTLS) {
            $mail->Port = 465;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->Port = 587;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->SMTPAuth = true;
        $mail->Username = $this->userName;
        $mail->Password = $this->password;

        if (!$sendFrom) {
            $sendFrom = $this->sendFrom ?? $_GLOBALS["EMAIL_QUEUE_SEND_FROM"] ?? $this->userName;
        }

        $mail->setFrom($sendFrom);

        return $mail;
    }
}
?>