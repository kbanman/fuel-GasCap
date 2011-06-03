<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2011 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Auth;

require_once APPPATH.'vendor/lightopenid/openid.php';
require_once APPPATH.'vendor/oauthsimple/OAuthSimple.php';

class Auth_Login_OpenID extends \Auth_Login_Driver {

	protected static $table_name;

	public static function _init()
	{
		\Config::load('gascap', true);
		\Config::load('simpleauth', true);
		static::$table_name = \Config::get('gascap.table_name');
	}

	/**
	 * @var  Database_Result  when login succeeded
	 */
	protected $user = null;

	/**
	 * @var  array  OpenID class config
	 */
	protected $config = array(
		'drivers' => array('group' => array('SimpleGroup')),
		'additional_fields' => array(),
	);

	/**
	 * Check for login
	 *
	 * @return  bool
	 */
	public function perform_check()
	{
		$email    = \Session::get('email');
		$login_hash  = \Session::get('login_hash');

		if ($this->user === null or (is_object($this->user) and $this->user->get('email') != $email))
		{
			$this->user = \DB::select()->where('email', '=', $email)->from(static::$table_name)->execute();
		}

		if ($this->user and $this->user->count() > 0 and $this->user->get('login_hash') === $login_hash)
		{
			return true;
		}

		return $this->user = false;
	}

	/**
	 * Login user
	 *
	 * @param	string
	 * @param	string
	 * @return	bool
	 */
	public function login($identity = '', $email = '')
	{
		if (empty($identity))
		{
			return false;
		}

		// Validate email/identifier combo, just to be sure
		// (even though this is potentially public info)
		$this->user = \DB::select()
			->where('identifier', '=', $identity)
			->from(static::$table_name)
			->execute();
		if ($this->user->count() === 0)
		{
			return $this->user = false;
		}

		\Session::set('email', $this->user->get('email'));
		\Session::set('login_hash', $this->create_login_hash());
		return true;
	}

	/**
	 * Logout user
	 *
	 * @return bool
	 */
	public function logout()
	{
		$this->user = null;
		\Session::delete('email');
		\Session::delete('login_hash');
		return true;
	}

	/**
	 * Create new local user
	 *
	 * @param	int          group id
	 * @return	string     activation code
	 */
	public function create_user($group = 1)
	{
		// Create activation code
		$activation = \Str::random('hexdec', 24);

		// Insert the new user
		$user = array(
			'email' => $email,
			'group' => (int) $group,
			'login_hash' => $activation,
			'last_login' => null,
			'created_at' => \Date::factory()->get_timestamp(),
		);
		$result = \DB::insert(static::$table_name)
			->set($user)
			->execute();

		return ($result[1] > 0) ? $activation : false;
	}

	/**
	 * Activate new user (Attach the local user to a provider key)
	 *
	 * @param	string    email
	 * @param	string    activation code produced by create_user()
	 * @param	string    provider's unique key
	 * @return	bool
	 */
	public function activate_user($activation, $email, $identifier, $oauth_token = null, $oauth_secret = null)
	{
		if (empty($activation) or empty($email) or empty($identifier))
		{
			return false;
		}

		// Validate the activation code
		$valid_record = \DB::select()
			->from(static::$table_name)
			->where('login_hash', '=', $activation)
			->where('last_login', '=', null)
			->execute();
		if ($valid_record->count() === 0)
		{
			return false;
		}
		
		// Insert the new deets
		$user = array(
			'email' => $email,
			'login_hash' => null,
			'last_login' => null,
			'updated_at' => \Date::factory()->get_timestamp(),
			'identifier' => $identifier,
		);

		// OAuth support
		if ( ! empty($oauth_token) and ! empty($oauth_token))
		{
			$user['oauth_token'] = $oauth_token;
			$user['oauth_token_secret'] = $oauth_token;
		}

		$result = \DB::update(static::$table_name)
			->set($user)
			->where('login_hash', '=', $activation)
			->execute();

		return (bool) $result;
	}

	/**
	 * Update a user's properties
	 * Note: Username cannot be updated, to update password the old password must be passed as old_password
	 *
	 * @param	array	properties to be updated including profile fields
	 * @param	string
	 * @return	bool
	 */
	public function update_user($values, $user_id = null)
	{
		$user_id = $user_id ?: $this->user->get('id');
		$current_values = \DB::select()
			->where('id', '=', $user_id)
			->from(static::$table_name)->execute();
		if (empty($current_values))
		{
			throw new \Auth_Exception('not_found');
		}
		if (isset($values['id']))
		{
			unset($values['id']);
		}
		if (array_key_exists('email', $values))
		{
			$values['email'] = filter_var(trim($values['email']), FILTER_VALIDATE_EMAIL);
			if ( ! $values['email'])
			{
				throw new \Auth_Exception('invalid_email');
			}
		}
		if (array_key_exists('group', $values) and ! is_numeric($values['group']))
		{
			unset($values['group']);
		}

		$affected_rows = \DB::update(static::$table_name)
			->set($values)
			->where('id', '=', $user_id)
			->execute();

		// Refresh user
		$this->user = \DB::select()->where('id', '=', $user_id)->from(static::$table_name)->execute();

		return $affected_rows > 0;
	}

	/**
	 * Deletes a given user
	 *
	 * @param	string
	 * @return	bool
	 */
	public function delete_user($user_id)
	{
		if (empty($user_id))
		{
			return false;
		}

		$affected_rows = \DB::delete(static::$table_name)
			->where('id', '=', $user_id)
			->execute();

		return $affected_rows > 0;
	}

	/**
	 * Creates a temporary hash that will validate the current login
	 *
	 * @return	string
	 */
	public function create_login_hash()
	{
		if (empty($this->user))
		{
			throw new \Auth_Exception('User not logged in, can\'t create login hash.');
		}

		$last_login = \Date::factory()->get_timestamp();
		$login_hash = sha1(\Config::get('gascap.login_hash_salt').$this->user->get('email').$last_login);

		\DB::update(static::$table_name)
			->set(array('last_login' => $last_login, 'login_hash' => $login_hash))
			->where('id', '=', $this->user->get('id'))
			->execute();

		return $login_hash;
	}

	/**
	 * Get the user's ID
	 *
	 * @return	array	containing this driver's ID & the user's ID
	 */
	public function get_user_id()
	{
		if (empty($this->user))
		{
			return false;
		}

		return array($this->id, (int) $this->user->get('id'));
	}

	/**
	 * Get the user's groups
	 *
	 * @return array	containing the group driver ID & the user's group ID
	 */
	public function get_groups()
	{
		if (empty($this->user))
		{
			return false;
		}

		return array(array('SimpleGroup', $this->user->get('group')));
	}

	/**
	 * Get the user's emailaddress
	 *
	 * @return	string
	 */
	public function get_email()
	{
		if (empty($this->user))
		{
			return false;
		}

		return $this->user->get('email');
	}

	/**
	 * Get the user's screen name
	 *
	 * @return	string
	 */
	public function get_screen_name()
	{
		return $this->get_email();
	}

	/**
	 * Extension of base driver method to default to user group instead of user id
	 */
	public function has_access($condition, $driver = null, $user = null)
	{
		if (is_null($user))
		{
			$user = reset($this->get_groups());
		}
		return parent::has_access($condition, $driver, $user);
	}

	public function request_openid_credentials($callback_url, $identity = 'https://www.google.com/accounts/o8/id')
	{
		$openid = new \LightOpenID;
		$openid->returnUrl = $callback_url;
		// Set google as the openID provider
		$openid->identity = $identity;
		// Get the user's info while we're at it
		$openid->required = \Config::get('gascap.required_info');
		$openid->optional = \Config::get('gascap.optional_info');

		if (\Config::get('gascap.oauth_enabled'))
		{
			// OAuth configuration
			$openid->oauth = array(
				'consumer' => \Config::get('gascap.oauth_consumer_key'),
				'scopes' => \Config::get('gascap.oauth_scopes'),
			);
		}
		header('Location: ' . $openid->authUrl());
	}

	public function validate_openid_credentials()
	{
		$openid = new \LightOpenID;

		// Make sure we have something going on
		if ( ! $openid->mode)
		{
			return null;
		}

		if ( ! $openid->validate())
		{
			return false;
		}

		// Find the oauth namespace
		$prefix = 'openid_'. substr(array_search('http://specs.openid.net/extensions/oauth/1.0', $openid->data), strlen('openid_ns_')).'_';

		// Check for oauth request token
		if (isset($openid->data[$prefix.'request_token']))
		{
			$oauth = new \OAuthSimple(\Config::get('gascap.oauth_consumer_key'), \Config::get('gascap.oauth_consumer_secret'));
			$result = $oauth->sign(array(
				'path' => \Config::get('gascap.oauth_url_get_access_token'),
				'parameters' => array(
					'oauth_token' => $openid->data[$prefix.'request_token'],
					'signatures' => array(
						'consumer_key' => \Config::get('gascap.oauth_consumer_key'),
						'shared_secret' => \Config::get('gascap.oauth_consumer_secrety'),
						'access_token' => $openid->data[$prefix.'request_token'],
					)
				)
			));
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_URL, $result['signed_url']);
			$r = curl_exec($ch);
			// Check for success
			if (curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200)
			{
				die('Request for access token wasn\'t successful. ('.$r.')');
			}
			// Grab the access token and secret
			preg_match('/oauth_token=(.*)&oauth_token_secret=(.*)/', $r, $matches);
			if (count($matches) != 3)
			{
				die ('Invalid response for access token and secret');
			}

			list(,$oauth_token, $oauth_token_secret) = $matches;
			$oauth_data = array(
				'oauth_token' => $access_token,
				'oauth_token_secret' => $token_secret,
			);
		}
		
		return array_merge(isset($oauth_data) ? $oauth_data : array(), array(
			'email' => $openid->data['openid_ext1_value_contact_email'],
			'identity' => $openid->data['openid_identity']
		));
	}
}

// end of file openid.php
