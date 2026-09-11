<?php
// Template only. Copy OUTSIDE the document root; never put real secrets here.
return [
    'smtp_host' => 'SET_SMTP_HOST',
    'smtp_port' => 465,
    'smtp_username' => 'SET_SMTP_USERNAME',
    'smtp_password' => 'SET_IN_PRIVATE_PRODUCTION_CONFIG',
    'smtp_encryption' => 'ssl',
    'mail_from' => 'SET_FROM_ADDRESS',
    'mail_to' => 'SET_RECIPIENT_ADDRESS',
    'allowed_origin' => 'SET_HTTPS_ORIGIN',
    'hmac_secret' => 'SET_RANDOM_SECRET_IN_PRIVATE_PRODUCTION_CONFIG',
    'state_dir' => 'SET_ABSOLUTE_PRIVATE_DIRECTORY',
];
