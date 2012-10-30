<?php

/**
 * Part of the Sentry package for FuelPHP.
 *
 * @package    Sentry
 * @version    1.0
 * @author     Cartalyst LLC
 * @license    MIT License
 * @copyright  2011 Cartalyst LLC
 * @link       http://cartalyst.com
 */

namespace Sentry;

/**
 * Sentry Auth Attempt Class
 *
 * @author Daniel Petrie
 */

class SentryAttemptsException extends \FuelException {}
class SentryUserSuspendedException extends \SentryAttemptsException {}

class Sentry_Attempts
{
	/**
	 * @var  string  Database instance
	 */
	protected static $db_instance = null;

	/**
	 * @var  string  Suspension table name
	 */
	protected static $table_suspend = null;

	/**
	 * @var  array  Stores suspension/limit config data
	 */
	protected static $limit = array();

	/**
	 * @var  string  Login id
	 */
	protected $login_id = null;

	/**
	 * @var  string  IP address
	 */
	protected $ip_address = null;

	/**
	 * @var  int  Number of login attempts
	 */
	protected $attempts = null;

	/**
	 * Attempts Constructor
	 *
	 * @param   string  user login
	 * @param   string  ip address
	 * @return  Sentry_Attempts
	 * @throws  SentryAttemptsException
	 */
	public function __construct($login_id = null, $ip_address = null)
	{
		static::$limit = array(
			'enabled'	=> true,
			'attempts'	=> (int) \Model_Settings::getItems('login_attempts'),
			'time'		=> (int) \Model_Settings::getItems('login_lockout_time'),
		);
		$this->login_id = $login_id;
		$this->ip_address = $ip_address;

		// limit checks
		if (static::$limit['enabled'] === true)
		{
			if ( ! is_int(static::$limit['attempts']) or static::$limit['attempts'] <= 0)
			{
				throw new \SentryAuthConfigException(__('sentry.invalid_limit_attempts'));
			}

			if ( ! is_int(static::$limit['time']) or static::$limit['time'] <= 0)
			{
				throw new \SentryAuthConfigException(__('sentry.invalid_limit_time'));
			}
		}

		$query = \Model_User_Suspend::find();

		if ($this->login_id)
		{
			$query = $query->where('login_id', $this->login_id);
		}

		if ($this->ip_address)
		{
			$query = $query->where('ip', $this->ip_address);
		}

		$result = $query->get();

		foreach ($result as &$row)
		{
			// check if last attempt was more than 15 min ago - if so reset counter
			if ($row['last_attempt_at'])
			{
				// create a last attempt date object
				$la = \Carbon::createFromFormat('Y-m-d H:i:s', $row['last_attempt_at']);

				if ($la->diffInSeconds($la->copy()->addMinutes(static::$limit['time'])) <= 0)
				{
					$this->clear($row['login_id'], $row['ip']);
					$row['attempts'] = 0;
				}
			}

			// check unsuspended time and clear if time is > than it
			if ($row['unsuspend_at'])
			{
				// create an unsuspend at date object
				$ua = \Carbon::createFromFormat('Y-m-d H:i:s', $row['unsuspend_at']);

				if ($ua->diffInSeconds(null) <= 0)
				{
					$this->clear($row['login_id'], $row['ip']);
					$row['attempts'] = 0;
				}
			}
		}

		if (count($result) > 1)
		{
			$this->attempts = $result;
		}
		elseif ($result)
		{
			$row = reset($result);

			$this->attempts = (int) $row->attempts;
		}
		else
		{
			$this->attempts = 0;
		}
	}

	/**
	 * Check Number of Login Attempts
	 *
	 * @return  int
	 */
	public function get()
	{
		return $this->attempts;
	}

	/**
	 * Gets attempt limit number
	 *
	 * @return  int
	 */
	 public function getLimit()
	 {
	 	return static::$limit['attempts'];
	 }

	/**
	 * Add Login Attempt
	 *
	 * @param string
	 * @param int
	 */
	public function add()
	{
		// make sure a login id and ip address are set
		if (empty($this->login_id) or empty($this->ip_address))
		{
			throw new \SentryAttemptsException(__('sentry.login_ip_required'));
		}

		// this shouldn't happen, but put it just to make sure
		if (is_array($this->attempts))
		{
			throw new \SentryAttemptsException(__('sentry.single_user_required'));
		}

		if ($this->attempts)
		{
			// find the record
			$record = \Model_User_Suspend::getItem(array(
				'login_id' 	=> $this->login_id,
				'ip' 		=> $this->ip_address
			));
			
			// update the record
			$result = \Model_User_Suspend::updateItem($record->id, array(
				'attempts' 			=> ++$this->attempts,
				'last_attempt_at' 	=> \Carbon::now()->toDateTimeString(),
			));
		}
		else
		{
			$result = \Model_User_Suspend::createItem(array(
				'login_id' 			=> $this->login_id,
				'ip' 				=> $this->ip_address,
				'attempts' 			=> ++$this->attempts,
				'last_attempt_at' 	=> \Carbon::now()->toDateTimeString(),
			));
		}
	}

	/**
	 * Clear Login Attempts
	 *
	 * @param string
	 * @param string
	 */
	public function clear()
	{
		if ($this->login_id)
		{
			$query = \Model_User_Suspend::clearItem(array('login_id' => $this->login_id));
		}

		if ($this->ip_address)
		{
			$query = \Model_User_Suspend::clearItem(array('ip' => $this->ip_address));
		}

		$this->attempts = 0;
	}

	/**
	 * Suspend
	 *
	 * @param string
	 * @param int
	 */
	public function suspend()
	{
		if (empty($this->login_id) or empty($this->ip_address))
		{
			throw new \SentryUserSuspendedException(__('sentry.login_ip_required'));
		}
		
		// find the record
		$record = \Model_User_Suspend::find()
			->where('login_id', $this->login_id)
			->where('ip', $this->ip_address)
			->where('unsuspend_at', null)
			->or_where('unsuspend_at', 0)
			->get();

		// get the current time
		$now = \Carbon::now();
		
		// update the record
		$result = \Model_User_Suspend::updateItem($record->id, array(
			'suspended_at' => $now->toDateTimeString(),
			'unsuspend_at' => $now->copy()->addMinutes(static::$limit['time'])->toDateTimeString()
		));

		// get the user
		$u = \Model_User::getItem($login_column_value, 'email');

		// create an event
		\SystemEvent::add(false, '[[event.login.suspend|{{'.$u->name.'}}]]');

		return \Login\Controller_Login::SUSPEND_START;
	}
}