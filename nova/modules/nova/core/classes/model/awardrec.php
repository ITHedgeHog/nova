<?php
/**
 * Awards Received Model
 *
 * @package		Nova
 * @category	Models
 * @author		Anodyne Productions
 * @copyright	2011 Anodyne Productions
 * @version		3.0
 */
 
class Model_AwardRec extends Orm\Model {
	
	public static $_table_name = 'awards_received';
	
	public static $_properties = array(
		'id' => array(
			'type' => 'int',
			'constraint' => 8,
			'auto_increment' => true),
		'receive_character_id' => array(
			'type' => 'int',
			'constraint' => 8),
		'receive_user_id' => array(
			'type' => 'int',
			'constraint' => 8),
		'nominate_user_id' => array(
			'type' => 'int',
			'constraint' => 8),
		'award_id' => array(
			'type' => 'int',
			'constraint' => 5),
		'date' => array(
			'type' => 'bigint',
			'constraint' => 20),
		'reason' => array(
			'type' => 'text'),
	);
}
