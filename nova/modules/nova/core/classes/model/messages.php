<?php
/**
 * Messages Model
 *
 * @package		Nova
 * @category	Models
 * @author		Anodyne Productions
 * @copyright	2011 Anodyne Productions
 * @version		3.0
 */
 
class Model_Messages extends Orm\Model {
	
	public static $_table_name = 'messages';
	
	public static $_properties = array(
		'id' => array(
			'type' => 'int',
			'constraint' => 8,
			'auto_increment' => true),
		'key' => array(
			'type' => 'string',
			'constraint' => 255,
			'default' => ''),
		'label' => array(
			'type' => 'string',
			'constraint' => 255,
			'default' => ''),
		'content' => array(
			'type' => 'text'),
		'type' => array(
			'type' => 'enum',
			'constraint' => "'title','message','other'",
			'default' => 'message'),
		'protected' => array(
			'type' => 'tinyint',
			'constraint' => 1,
			'default' => 0),
	);
	
	/**
	 * Get a specific message from the database.
	 *
	 *     Model_Messages::get_message('welcome_msg');
	 *     Model_Messages::get_message('welcome_msg', false);
	 *
	 * @access	public
	 * @param	string	the key of the message to get
	 * @param	boolean	whether to pull only the value or the full object
	 * @return	mixed	a string if $value_only is TRUE, else an object
	 */
	public static function get_message($key, $value_only = true)
	{
		$query = static::find()->where('key', $key)->get_one();
		
		if ($value_only === true)
		{
			return $query->content;
		}
		
		return $query;
	}
}
