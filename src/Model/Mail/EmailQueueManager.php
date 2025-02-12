<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class EmailQueueManager extends DataAccess
{
    private $userName;
    private $password;
    private $useGmail;
    private $smtpPort;
    private $hostAddress;
    public  $sendFrom;
    public  $debugLevel;

    public function __construct($p_db, $options = [])
    {
        global $_GLOBALS;

        $this->userName = $_GLOBALS["EMAIL_QUEUE_USER"] ?? null;
        $this->password = $_GLOBALS["EMAIL_QUEUE_PASSWORD"] ?? null;
        $this->smtpPort = $_GLOBALS["EMAIL_QUEUE_PORT"] ?? 465;
        $this->hostAddress = $_GLOBALS["EMAIL_QUEUE_SMTP_HOST"] ?? null;
        $this->sendFrom = $_GLOBALS["EMAIL_QUEUE_SEND_FROM"] ?? null;
        $this->debugLevel = $_GLOBALS["EMAIL_QUEUE_DEBUG_LEVEL"] ?? 0;

        if (!$this->userName || !$this->password || !$this->hostAddress || !$this->sendFrom) {
            echo "Skipping email queue setup due to missing configurations.\n";
            return;
        }

        parent::__construct($p_db, $options);
    }

    public function register()
    {
        // Implementación del método register
    }

    function verifyEmailDomain($email)
    {
        // Implementación del método verifyEmailDomain
    }

    function verifyEmailString($toCheck) 
    {
        // Implementación del método verifyEmailString
    }

    public function addAlertToQueue($options = [])
    {
        // Implementación del método addAlertToQueue
    }

    public function addDictionaryToQueue($dictionary) 
    {
        // Implementación del método addDictionaryToQueue
    }
}