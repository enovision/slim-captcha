# Slim Captcha

This easy to use captcha package is based on the captcha helper from CodeIgniter (Ellislab).

## Installing

Use Composer to install Slim Captcha into your project:
```
composer require enovision/slim-captcha
```

or clone it from github
```
git clone https://github.com/enovision/slim-captcha
```

## Requirements
- akrabat/rka-ip-address-middleware

## Usage With Slim 3

### Requirements

* the 'gd' extension (gdlib) has to be loaded (for creating the image)

* the `img_path` as a setting has to be set to a folder in the public folder like f.e. `captcha` (this folder has to be writeable (chmod 775)
  
  like: $_SERVER['DOCUMENT_ROOT'] . '/captcha/'  (as is folder in public_html/captcha or private_html/..)

* the img_url have to be set in the settings

  like: 'http(s)://' . $_SERVER['SERVER_NAME'] . '/captcha/'

### Database table 'captcha'

The following table is required to make this package successful. Below
you find the code for MySQL, but you can also implement your own requirement.
All the database activity is done with callback functions, so you can have your
own implementations work as well.

```
CREATE TABLE `captcha` (
  `captcha_id` BIGINT(13) UNSIGNED NOT NULL AUTO_INCREMENT,
  `captcha_time` INT(10) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `word` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`captcha_id`),
  INDEX `word` (`word`)
)
COLLATE='latin1_swedish_ci'
ENGINE=InnoDB
AUTO_INCREMENT=0;
```

Sample callbacks with Eloquent ORM in Slim 3 as can be found
in the settings file:

```
<?php
use Illuminate\Database\Capsule\Manager as DB;

$settings_captcha = [
    ... all the other settings ...,
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

```

### Settings 

The settings for a Captcha object can be delivered from the object's default values,
from the settings file or when creating the object.

Order of importance:
* overruling settings when creating the captcha object
* settings.php in /config/captcha/settings.php
* defaults in this class

The default location of the settings.php file is `<same-folder-as-app-is>/config/captcha/settings.php`.
In the root of this composer package you can find a sample settings.php file, which you can copy to your
preferred location (rather the location just mentionend).

When creating the captcha you can add the alternative location at object creation time:

```
$captcha = new \Enovision\Slim\Captcha($this, $request, $response, [
    'settings_path' => INC_ROOT . '/alternative/location/settings.php',
]);

$captcha();
```

### Creating a captcha

Creating a captcha could not be easier:
```
$captcha = new \Enovision\Slim\Captcha($this, $request, $response);
$captcha();
```
That's all. It will echo the captcha code in an html 'img' tag.

If you don't want to echo it directly on your client's display, you can also 
execute:
```
$captcha = new \Enovision\Slim\Captcha($this, $request, $response);
$captcha = $captcha(false);
```
This will return an array with the following format:
```
[
  'word' => generated word in the image,
  'time' => time of creation of the captcha,
  'image' => the image itself, including the html 'img' tag,
  'url' => url of the image,
  'filename' => filename of the generated image,
  'ip_address' => ip address of the client
]
```
When creating the image, it will save also a record in the 'captcha' table in your database.

### Validating a captcha

```
$captcha = new \Enovision\Slim\Captcha($this, $request, $response);
$valid = $captcha->validateCaptcha($typed_in_word_from_a_form);
```
This will check the validity with a valid record in the database table 'captcha'.
It returns `true` or `false`.

### Cleaning expired captcha's

Old records that have been expired are cleaned every time a captcha is validated.
The validations are kept alive equal to the 'expiration' setting (in seconds).

### Fonts
In the folder /assets/fonts of this package you will find a nice font for your captcha images.
When you would like to use it, copy this to the assets folder in your public part of the site/application.
Then adjust the following setting in the settings file:
```
'font_path' => $_SERVER['DOCUMENT_ROOT'] . '/somewhere/in/public/fonts/00118_20thCenturyFontBold.ttf',
```

## Credits
[Ellislab](https://www.ellislab.com), for creating this functionality in CodeIgniter 3.

[CodeIgniter, British Columbia Institute of Technology](https://www.codeigniter.com), CodeIgniter