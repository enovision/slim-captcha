<?php
/**
 * @package     Captcha
 * @author      J.J. van de Merwe
 * @credits 	EllisLab Dev Team
 * @copyright	Copyright (c) 2017, Enovision GmbH, Johan van de Merwe
 * @license 	http://opensource.org/licenses/MIT	MIT License *
 *
 * This package is a transition of the CodeIgniter(TM) captcha_helper functionality
 * as can be found in CodeIgniter version 3.1. This helper class, which resides in the
 * folder app/helpers, generates and validates captcha's without the need of other dependencies.
 *
 * When creating a captcha it will be saved in your database in the table 'captcha'. The table
 * actions are processed through callback functions that are defined in the captcha settings file
 * which is in folder /config/captcha/settings.php. You can also hand over the settings, or overrule
 * the defaults when creating the captcha object. The order of importance is:
 *
 * 1. overruling settings when creating the captcha object
 * 2. settings.php in /config/captcha/settings.php
 * 3. defaults in this class
 *
 * The following requirements have to be fulfilled:
 *
 * 1. the 'gd' extension (gdlib) has to be loaded (for creating the image)
 * 2. the img_path have to be set in the settings (this folder has to be writeable (chmod 775)
 *    $_SERVER['DOCUMENT_ROOT'] . '/captcha/'  (as is folder in public_html/captcha or private_html/..)
 * 3. the img_url have to be set in the settings
 *   'http(s)://' . $_SERVER['SERVER_NAME'] . '/captcha/'
 *
 * Required in your database:
 *
 * CREATE TABLE `captcha` (
 *    `captcha_id` BIGINT(13) UNSIGNED NOT NULL AUTO_INCREMENT,
 *    `captcha_time` INT(10) NOT NULL,
 *    `ip_address` VARCHAR(45) NOT NULL,
 *    `word` VARCHAR(20) NOT NULL,
 *    PRIMARY KEY (`captcha_id`),
 *    INDEX `word` (`word`)
 * )
 * COLLATE='latin1_swedish_ci'
 * ENGINE=InnoDB
 * AUTO_INCREMENT=0;
 *
 * For getting the ip address of the client (suggestion):
 *
 * composer require akrabat/rka-ip-address-middleware
 *
 * Then in app.php:
 *
 * if (isset($container->get('retrieve-ip-middleware')['checkProxyHeaders'])) {
 *    $checkProxyHeaders = $container->get('retrieve-ip-middleware')['checkProxyHeaders'];
 *    $trustedProxies = $container->get('retrieve-ip-middleware')['trustedProxies'];
 *    $app->add(new RKA\Middleware\IpAddress($checkProxyHeaders, $trustedProxies));
 * }
 *
 * then you can get the address:
 *
 * $this->ipAddress = $request->getAttribute('ip_address');
 *
 * @property null word
 * @property null img_path
 * @property null img_url
 * @property null img_width
 * @property null img_height
 * @property null font_path
 * @property null expiration
 * @property null word_length
 * @property null font_size
 * @property null img_id
 * @property null pool
 * @property null colors
 * @property null callbackSave
 * @property null callbackValidate
 * @property null callbackCleanUp
 * @property null settings_path
 *
 */
namespace Enovision\Slim;

class Captcha
{
    protected $defaults;
    protected $container;
    protected $request;
    private $ipAddress;

    function __construct($container, $request, $response, $defaults = [])
    {
        $this->ipAddress = $request->getAttribute('ip_address');

        $this->defaults = [
            'word' => '',
            'img_path' => '',
            'img_url' => '',
            'img_width' => '150',
            'img_height' => '30',
            'font_path' => '',
            'expiration' => 3600,
            'word_length' => 8,
            'font_size' => 16,
            'settings_path' => INC_ROOT . '/config/captcha/settings.php',
            'img_id' => '',
            'pool' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'colors' => [
                'background' => [255, 255, 255],
                'border' => [153, 102, 102],
                'text' => [204, 153, 153],
                'grid' => [255, 182, 182]
            ],
            'callbackSave' => function ($captcha) {
                /**
                 * with Illuminate/Database/Manager
                 *
                 * at the top of your program:
                 *
                 * use Illuminate\Database\Capsule\Manager as DB;
                 *
                 * in this callback:
                 *
                 * DB::table('captcha')->insert([
                 *   'captcha_time' => $captcha['time'],
                 *   'ip_address' => $captcha['ip_address'],
                 *   'word' => $captcha['word']
                 * ]);
                 *
                 */
            },
            'callbackCleanUp' => function ($expiration) {
                /**
                 * with Illuminate/Database/Manager
                 *
                 * at the top of your program:
                 *
                 * use Illuminate\Database\Capsule\Manager as DB;
                 *
                 * in this callback:
                 *
                 * DB::table('captcha')
                 *   ->where('captcha_time', '<', $expiration)
                 *   ->delete();
                 *
                 */
            },
            'callbackValidate' => function ($word, $ip_address, $expiration) {
                /**
                 * with Illuminate/Database/Manager
                 *
                 * at the top of your program:
                 *
                 * use Illuminate\Database\Capsule\Manager as DB;
                 *
                 * in this callback:
                 *
                 * $count = DB::table('captcha')
                 *             ->where('word', $word)
                 *             ->where('ip_address', $ip_address)
                 *             ->where('captcha_time', '>', $expiration)
                 *             ->count();
                 *
                 * return $count;
                 *
                 */

                return 0;
            }
        ];
        # Merge settings from constructor
        $this->defaults = array_merge($this->defaults, $defaults);
        
        $this->container = $container;
        $this->request = $request;
        $this->response = $response;

        # load the settings files or any other defaults
        $settings = [];
        $pathSettings = $this->settings_path;

        if (file_exists($pathSettings)) {
            $settings = require($pathSettings);
        }

        $this->defaults = array_merge($this->defaults, $settings, $defaults);
        unset($settings);
    }

    public function __invoke($return = false)
    {
        return $this->refreshCaptcha($return);
    }

    public function __get($property)
    {
        return isset($this->defaults[$property]) ? $this->defaults[$property] : null;
    }

    public function refreshCaptcha($return = false)
    {
        $captcha = $this->createCaptcha();

        $callback = $this->callbackSave;

        if (is_callable($callback)) {
            $callback($captcha);
        }

        // Return the Captcha Settings
        if (!$return) return $captcha;

        echo $captcha['image'];

    }

    /**
     * Create a new CAPTCHA image/array
     *
     * @param    string $img_path path to create the image in
     * @param    string $img_url URL to the CAPTCHA image folder
     * @param    string $font_path server path to font
     * @return    array
     */
    function createCaptcha($img_path = '', $img_url = '', $font_path = '')
    {
        $img_path = empty($img_path) ? $this->img_path : $img_path;
        $img_url = empty($img_url) ? $this->img_url : $img_url;
        $font_path = empty($font_path) ? $this->font_path : $font_path;

        if ($img_path === '' OR $img_url === ''
            OR !is_dir($img_path) OR !$this->isDirWritable($img_path) // TODO nakijken
            OR !extension_loaded('gd')
        ) {
            return false;
        }

        // -----------------------------------
        // Remove old images
        // -----------------------------------

        $now = microtime(TRUE);

        $current_dir = @opendir($img_path);
        while ($filename = @readdir($current_dir)) {
            if (in_array(substr($filename, -4), ['.jpg', '.png'])
                && (str_replace(array('.jpg', '.png'), '', $filename) + $this->expiration) < $now
            ) {
                @unlink($img_path . $filename);
            }
        }

        @closedir($current_dir);

        // -----------------------------------
        // Do we have a "word" yet?
        // -----------------------------------

        $word = $this->word;
        $word_length = $this->word_length;
        $pool = $this->pool;

        if (empty($word)) {
            $word = '';
            $pool_length = strlen($pool);
            $rand_max = $pool_length - 1;

            // PHP7 or a suitable polyfill
            if (function_exists('random_int')) {
                try {
                    for ($i = 0; $i < $word_length; $i++) {
                        $word .= $this->pool[random_int(0, $rand_max)];
                    }
                } catch (Exception $e) {
                    // This means fallback to the next possible
                    // alternative to random_int()
                    $word = '';
                }
            }
        }

        if (empty($word)) {
            // Nobody will have a larger character pool than
            // 256 characters, but let's handle it just in case ...
            //
            // No, I do not care that the fallback to mt_rand() can
            // handle it; if you trigger this, you're very obviously
            // trying to break it. -- Narf
            if ($pool_length > 256) {
                return false;
            }

            if (($bytes = $this->getRandomBytes($pool_length)) !== false) {
                $byte_index = $word_index = 0;
                while ($word_index < $word_length) {
                    // Do we have more random data to use?
                    // It could be exhausted by previous iterations
                    // ignoring bytes higher than $rand_max.
                    if ($byte_index === $pool_length) {
                        // No failures should be possible if the
                        // first getRandomBytes() call didn't
                        // return false, but still ...
                        for ($i = 0; $i < 5; $i++) {
                            if (($bytes = $this->getRandomBytes($pool_length)) === false) {
                                continue;
                            }

                            $byte_index = 0;
                            break;
                        }

                        if ($bytes === false) {
                            // Sadly, this means fallback to mt_rand()
                            $word = '';
                            break;
                        }
                    }

                    list(, $rand_index) = unpack('C', $bytes[$byte_index++]);
                    if ($rand_index > $rand_max) {
                        continue;
                    }

                    $word .= $pool[$rand_index];
                    $word_index++;
                }
            }
        }

        if (empty($word)) {
            for ($i = 0; $i < $word_length; $i++) {
                $word .= $pool[mt_rand(0, $rand_max)];
            }
        } elseif (!is_string($word)) {
            $word = (string)$word;
        }

        // -----------------------------------
        // Determine angle and position
        // -----------------------------------

        $img_height = intval($this->img_height);
        $img_width = intval($this->img_width);
        $colors = $this->colors;
        $font_size = $this->font_size;
        $img_id = $this->img_id;

        $length = strlen($word);
        $angle = ($length >= 6) ? mt_rand(-($length - 6), ($length - 6)) : 0;
        $x_axis = mt_rand(6, (360 / $length) - 16);
        $y_axis = ($angle >= 0) ? mt_rand($img_height, $img_width) : mt_rand(6, $img_height);

        // Create image
        // PHP.net recommends imagecreatetruecolor(), but it isn't always available
        $im = function_exists('imagecreatetruecolor')
            ? imagecreatetruecolor($img_width, $img_height)
            : imagecreate($img_width, $img_height);

        // -----------------------------------
        //  Assign colors
        // ----------------------------------

        is_array($colors) OR $colors = $this->colors;

        foreach (array_keys($colors) as $key) {
            // Check for a possible missing value
            is_array($colors[$key]) OR $colors[$key];
            $colors[$key] = imagecolorallocate($im, $colors[$key][0], $colors[$key][1], $colors[$key][2]);
        }

        // Create the rectangle
        ImageFilledRectangle($im, 0, 0, $img_width, $img_height, $colors['background']);

        // -----------------------------------
        //  Create the spiral pattern
        // -----------------------------------
        $theta = 1;
        $thetac = 7;
        $radius = 16;
        $circles = 20;
        $points = 32;

        for ($i = 0, $cp = ($circles * $points) - 1; $i < $cp; $i++) {
            $theta += $thetac;
            $rad = $radius * ($i / $points);
            $x = ($rad * cos($theta)) + $x_axis;
            $y = ($rad * sin($theta)) + $y_axis;
            $theta += $thetac;
            $rad1 = $radius * (($i + 1) / $points);
            $x1 = ($rad1 * cos($theta)) + $x_axis;
            $y1 = ($rad1 * sin($theta)) + $y_axis;
            imageline($im, $x, $y, $x1, $y1, $colors['grid']);
            $theta -= $thetac;
        }

        // -----------------------------------
        //  Write the text
        // -----------------------------------

        $use_font = ($font_path !== '' && file_exists($font_path) && function_exists('imagettftext'));
        if ($use_font === false) {
            ($font_size > 5) && $font_size = 5;
            $x = mt_rand(0, $img_width / ($length / 3));
            $y = 0;
        } else {
            ($font_size > 30) && $font_size = 30;
            $x = mt_rand(0, $img_width / ($length / 1.5));
            $y = $font_size + 2;
        }

        for ($i = 0; $i < $length; $i++) {
            if ($use_font === false) {
                $y = mt_rand(0, $img_height / 2);
                imagestring($im, $font_size, $x, $y, $word[$i], $colors['text']);
                $x += ($font_size * 2);
            } else {
                $y = mt_rand($img_height / 2, $img_height - 3);
                imagettftext($im, $font_size, $angle, $x, $y, $colors['text'], $font_path, $word[$i]);
                $x += $font_size;
            }
        }

        // Create the border
        imagerectangle($im, 0, 0, $img_width - 1, $img_height - 1, $colors['border']);

        // -----------------------------------
        //  Generate the image
        // -----------------------------------
        $img_url = rtrim($img_url, '/') . '/';

        if (function_exists('imagejpeg')) {
            $img_filename = $now . '.jpg';
            imagejpeg($im, $img_path . $img_filename);
        } elseif (function_exists('imagepng')) {
            $img_filename = $now . '.png';
            imagepng($im, $img_path . $img_filename);
        } else {
            return false;
        }

        $img = sprintf('<img %s src="%s" style="width:%spx;height:%spx;border:0;"alt="captcha"/>',
            $img_id === '' ? '' : 'id="' . $img_id . '"',
            $img_url . $img_filename,
            $img_width,
            $img_height
        );

        ImageDestroy($im);

        return [
            'word' => $word,
            'time' => $now,
            'image' => $img,
            'url' => $img_url . $img_filename,
            'filename' => $img_filename,
            'ip_address' => $this->ipAddress
        ];
    }

    public function validateCaptcha($word)
    {
        $expiration = time() - $this->expiration;
        $ip_address = $this->ipAddress;

        $callback = $this->callbackCleanUp;
        if (is_callable($callback)) {
            $callback($expiration);
        }

        $count = 0;

        $callback = $this->callbackValidate;
        if (is_callable($callback)) {
            $count = $callback($word, $ip_address, $expiration);
        }

        return $count > 0;
    }

    /**
     * Tests for file writability
     *
     * is_writable() returns TRUE on Windows servers when you really can't write to
     * the file, based on the read-only attribute.  is_writable() is also unreliable
     * on Unix servers if safe_mode is on.
     *
     * @access    private
     * @return    bool
     */
    private function isDirWritable($file)
    {
        // If we're on a Unix server with safe_mode off we call is_writable
        if (DIRECTORY_SEPARATOR == '/' AND @ini_get("safe_mode") == false) {
            return is_writable($file);
        }

        // For windows servers and safe_mode "on" installations we'll actually
        // write a file then read it.  Bah...
        if (is_dir($file)) {
            $file = rtrim($file, '/') . '/' . md5(rand(1, 100));

            if (($fp = @fopen($file, FOPEN_WRITE_CREATE)) === false) {
                return false;
            }

            fclose($fp);
            @chmod($file, DIR_WRITE_MODE);
            @unlink($file);
            return TRUE;
        } elseif (($fp = @fopen($file, FOPEN_WRITE_CREATE)) === false) {
            return false;
        }

        fclose($fp);
        return TRUE;
    }

    /**
     * Get random bytes
     *
     * @param    int $length Output length
     * @return    string
     */
    private function getRandomBytes($length)
    {
        if (empty($length) OR !ctype_digit((string)$length)) {
            return false;
        }

        // Unfortunately, none of the following PRNGs is guaranteed to exist ...
        if (defined('MCRYPT_DEV_URANDOM') && ($output = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM)) !== false) {
            return $output;
        }


        if (is_readable('/dev/urandom') && ($fp = fopen('/dev/urandom', 'rb')) !== false) {
            // Try not to waste entropy ...
            is_php('5.4') && stream_set_chunk_size($fp, $length);
            $output = fread($fp, $length);
            fclose($fp);
            if ($output !== false) {
                return $output;
            }
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            return openssl_random_pseudo_bytes($length);
        }

        return false;
    }
}
