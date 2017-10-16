<?php

use Illuminate\Database\Capsule\Manager as DB;

$settings_captcha = [
    'img_path' => $_SERVER['DOCUMENT_ROOT'] . '/captcha/',
    'img_url' => 'https://' . $_SERVER['SERVER_NAME'] . '/captcha/',
    'img_width' => '300px',
    'img_height' => '60px',
    'img_id' => 'captcha-image',
    'expiration' => 3600,
    'font_path' => $_SERVER['DOCUMENT_ROOT'] . '/server/assets/fonts/00118_20thCenturyFontBold.ttf',
    'word_length' => 6,
    'font_size' => 30,
    'colors' => [
        'background' => [255, 255, 255],
        'border' => [204, 204, 204],
        'text' => [255, 170, 0],
        'grid' => [255, 212, 85]
    ],
    'callbackSave' => function ($captcha) {
        DB::table('captcha')->insert([
            'captcha_time' => $captcha['time'],
            'ip_address' => $captcha['ip_address'],
            'word' => $captcha['word']
        ]);
    },
    'callbackCleanUp' => function ($expiration) {
        DB::table('captcha')
          ->where('captcha_time', '<', $expiration)
          ->delete();
    },
    'callbackValidate' => function ($word, $ip_address, $expiration) {
        $count = DB::table('captcha')
          ->where('word', $word)
          ->where('ip_address', $ip_address)
          ->where('captcha_time', '>', $expiration)
          ->count();

        return $count;
    }
];

return $settings_captcha;