<?php
return array(
  'provider' => getenv('PAY_PROVIDER') ?: 'epay',
  'epay' => array(
    'apiurl' => getenv('EPAY_API_URL') ?: 'https://xa.2xrr.com/xpay/epay/',
    'pid' => getenv('EPAY_PID') ?: '10170',
    'key' => getenv('EPAY_KEY') ?: 'OKWnskBjMCJZNTKoWHcb',
    'sign_type' => getenv('EPAY_SIGN_TYPE') ?: 'MD5',
  ),
  'codepay' => array(
    'apiurl' => getenv('CODEPAY_API_URL') ?: '',
    'pid' => getenv('CODEPAY_PID') ?: '',
    'key' => getenv('CODEPAY_KEY') ?: '',
    'sign_type' => getenv('CODEPAY_SIGN_TYPE') ?: 'MD5',
  ),
  'updated_at' => '2026-04-07 00:00:00',
);
