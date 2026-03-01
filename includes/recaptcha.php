<?php
define('RECAPTCHA_SITE_KEY', '---');
define('RECAPTCHA_SECRET_KEY', '---');

function verifyRecaptcha($response) {
    if (empty($response)) {
        return ['success' => false, 'message' => 'Подтвердите, что вы не робот (reCAPTCHA)'];
    }

    $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $response
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 30,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    $context  = stream_context_create($options);
    $result = @file_get_contents($verify_url, false, $context);

    if ($result === false) {
        return ['success' => false, 'message' => 'Ошибка проверки reCAPTCHA (не удалось соединиться с сервером)'];
    }

    $response_data = json_decode($result, true);

    if (!$response_data || !isset($response_data['success']) || !$response_data['success']) {
        return ['success' => false, 'message' => 'Не пройдена проверка reCAPTCHA'];
    }

    return ['success' => true, 'message' => ''];
}
