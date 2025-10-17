<?php
return array (
  'provider' => 'gmail',
  'gmail' => 
  array (
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'security' => 'tls',
    'auth' => true,
    'username' => 'tplearnph@gmail.com',
    'password' => 'naojszvbyvybqazh',
    'from_name' => 'TPLearn',
    'from_email' => 'tplearnph@gmail.com',
  ),
  'sendgrid' => 
  array (
    'host' => 'smtp.sendgrid.net',
    'port' => 587,
    'security' => 'tls',
    'auth' => true,
    'username' => 'apikey',
    'password' => '',
    'from_name' => 'TPLearn',
    'from_email' => '',
  ),
  'mailgun' => 
  array (
    'host' => 'smtp.mailgun.org',
    'port' => 587,
    'security' => 'tls',
    'auth' => true,
    'username' => '',
    'password' => '',
    'from_name' => 'TPLearn',
    'from_email' => '',
  ),
  'local' => 
  array (
    'host' => 'localhost',
    'port' => 1025,
    'security' => false,
    'auth' => false,
    'from_name' => 'TPLearn Development',
    'from_email' => 'dev@tplearn.local',
  ),
  'outlook' => 
  array (
    'host' => 'smtp-mail.outlook.com',
    'port' => 587,
    'security' => 'tls',
    'auth' => true,
    'username' => '',
    'password' => '',
    'from_name' => 'TPLearn',
    'from_email' => '',
  ),
  'templates' => 
  array (
    'verification_subject' => 'Verify Your Email Address - TPLearn',
    'base_url' => 'http://localhost/TPLearn',
  ),
  'debug' => 
  array (
    'enabled' => true,
    'level' => 2,
    'log_emails' => true,
  ),
);
