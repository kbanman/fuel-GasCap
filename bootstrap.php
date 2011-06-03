<?php
/**
 * @package    GasCap
 * @version    0.1
 * @author     Kelly Banman
 * @license    MIT License
 * @copyright  2011 Kelly Banman
 * @link       http://kellybanman.com
 */

Autoloader::add_classes(array(
	'Auth_Login_GasCap' => __DIR__.'/drivers/auth/login/gascap.php',
	'LightOpenID' => PKGPATH.'gascap'.DS.'vendor'.DS.'lightopenid'.DS.'openid.php',
	'OAuthSimple' => PKGPATH.'gascap'.DS.'vendor'.DS.'oauthsimple'.DS.'php'.DS.'OAuthSimple.php',
));


/* End of file bootstrap.php */