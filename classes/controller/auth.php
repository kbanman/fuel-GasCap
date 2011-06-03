<?php

class Controller_Auth extends Controller {

	public function action_index()
	{
		// Check if authenticated, try to authenticate if not
		if (Auth::instance()->perform_check())
		{
			$email = Auth::instance()->get_email();
			echo 'logged in as '.$email. ' <a href="'.Uri::create('auth/logout').'">Logout</a>';
		}
		else
		{
			echo 'Not logged in. <a href="'.Uri::create('auth/login').'">Login</a>';
		}

	}
	
	public function action_login()
	{
		$auth = Auth::instance();
		if ($auth->perform_check())
		{
			Response::redirect('auth');
		}

		if ($data = $auth->validate_openid_credentials())
		{
			if ( ! $auth->login($data['identity']))
			{
				print_r($data);
				die ('Couldn\'t login');
			}
			Response::redirect('auth');
		}

		// Send the user off to openid provider
		$auth->request_openid_credentials(Uri::create('auth/login'));

	}

	public function action_logout()
	{
		$auth = Auth::instance();
		$auth->logout();
		Response::redirect('auth');
	}

	public function action_openid_reset()
	{
		\DBUtil::truncate_table('users_openid');
		$auth = Auth::instance();
		echo '<p>'.$auth->create_user($group = 100).'</p>';
		echo '<p>'.$auth->create_user($group = 1).'</p>';
		echo '<p>'.$auth->create_user($group = 0).'</p>';
		echo 'done';
	}

	public function action_openid_activate($activation_code=null)
	{
		$auth = Auth::instance();
		$error = false;
		if ($activation = Input::post('activation'))
		{
			// Send the user off to openid provider
			$auth->request_openid_credentials(Uri::create('auth/openid_activate').'/'.$activation);
			$error = true;
			die ('err, not supposed to see this'.$activation);
		}
		elseif ($data = $auth->validate_openid_credentials())
		{
			// Try to activate the user
			if ($auth->activate_user($activation_code, $data['email'], $data['identity'], $data['oauth_token'], $data['oauth_token_secret']))
			{
				// Login
				if ($auth->login($data['identity']))
				{
					die('logged in!');
				}

				die ('You\'re activated!');
			}
			else
			{
				var_dump($activation_code);
				echo '<pre>';
				print_r($data);
				echo '</pre>';
				die ('failed activation');
			}
		}
		// Show the form
		$this->response->body = View::factory('auth/activation', array('error' => $error, 'activation' => Input::post('activation')));
	}
}