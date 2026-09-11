<?php
declare(strict_types=1);
namespace Nurlift\Contact;

function composeMail(array $config, array $lead): \PHPMailer\PHPMailer\PHPMailer
{
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPDebug = 0;
    $mail->Host = $config['smtp_host'];
    $mail->Port = $config['smtp_port'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp_username'];
    $mail->Password = $config['smtp_password'];
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]];
    $mail->Timeout = 15;
    $mail->getSMTPInstance()->Timelimit = 30;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($config['mail_from'], 'Nurlift');
    $mail->addAddress($config['mail_to']);
    $mail->addReplyTo($lead['email']);
    $mail->isHTML(false);
    $prefix = str_starts_with($lead['source'], 'Dropper') ? 'Dropper' : 'Nurlift';
    $mail->Subject = '[' . $prefix . ' Lead] ' . $lead['source'];
    $fields = ['Name/Nome' => 'name', 'Email' => 'email', 'Phone/Telefone' => 'phone',
        'Subject/Assunto' => 'subject', 'Source' => 'source', 'Page language' => 'page_language',
        'Originating page' => 'page', 'Server timestamp (UTC)' => 'timestamp', 'Request ID' => 'request_id'];
    $lines = [];
    foreach ($fields as $label => $key) $lines[] = $label . ': ' . $lead[$key];
    $mail->Body = implode("\n", $lines);
    return $mail;
}

function sendMail(array $config, array $lead): void
{
    if (!composeMail($config, $lead)->send()) throw new \RuntimeException('delivery_failed');
}
