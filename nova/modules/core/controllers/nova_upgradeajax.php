<?php
/**
 * Upgrade Ajax controller
 *
 * @package		Nova
 * @category	Controller
 * @author		Anodyne Productions
 * @copyright	2010-11 Anodyne Productions
 * @version		2.0
 */

/**
 * final_password
 * final_roles
 * quick_install
 * specs
 * tour
 * user_awards
 * user_defaults
 * user_logs
 * user_news
 * user_posts
 * welcome
 */

abstract class Nova_upgradeajax extends Controller {
	
	public function __construct()
	{
		parent::__construct();
		
		$this->load->database();
		$this->load->dbforge();
		$this->load->dbutil();
	}
	
	public function upgrade_awards()
	{
		// start by getting a count of the number of items in the awards table
		$count = $this->db->query("SELECT awardid FROM sms_awards");
		$count_old = $count->num_rows();
		
		// drop the nova version of the table
		$this->dbforge->drop_table('awards');
		
		try {
			// copy the sms version of the table along with all its data
			$this->db->query("CREATE TABLE ".$this->db->dbprefix."awards SELECT * FROM sms_awards");
			
			// rename the fields
			$fields = array(
				'awardid' => array(
					'name' => 'award_id',
					'type' => 'INT',
					'constraint' => 5),
				'awardName' => array(
					'name' => 'award_name',
					'type' => 'VARCHAR',
					'constraint' => 255),
				'awardImage' => array(
					'name' => 'award_image',
					'type' => 'VARCHAR',
					'constraint' => 100),
				'awardOrder' => array(
					'name' => 'award_order',
					'type' => 'INT',
					'constraint' => 5),
				'awardDesc' => array(
					'name' => 'award_desc',
					'type' => 'TEXT'),
				'awardCat' => array(
					'name' => 'award_cat',
					'type' => 'ENUM',
					'constraint' => "'ic','ooc','both'",
					'default' => 'ic'),
			);
			
			// modify the columns
			$this->dbforge->modify_column('awards', $fields);
			
			// add the award_display column
			$add = array(
				'award_display' => array(
					'type' => 'ENUM',
					'constraint' => "'y','n'",
					'default' => 'y')
			);
			
			// do the add action
			$this->dbforge->add_column('awards', $add);
			
			// make award_id auto increment and the primary key
			$this->db->query("ALTER TABLE ".$this->db->dbprefix."awards MODIFY COLUMN `award_id` INT(5) auto_increment primary key");
			
			// get the number of records in the new table
			$count_new = $this->db->count_all('awards');
			
			if ($count_new == $count_old)
			{
				$retval = array(
					'code' => 1,
					'message' => ''
				);
			}
			else
			{
				$retval = array(
					'code' => 0,
					'message' => "Not all of the awards were transferred to the Nova format"
				);
			}
			
			$this->dbutil->optimize_table('awards');
		} catch (Exception $e) {
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_characters()
	{
		$this->load->model('characters_model', 'char');
		$this->load->model('users_model', 'user');
		
		try {
			// get the characters
			$query = $this->db->query("SELECT * FROM sms_crew");
			
			// the user array
			$userarray = array();
			
			// an array of character IDs
			$charIDs = array('' => 0);
			
			$langValues = array(
				'field_type' => 'text',
				'field_name' => 'languages',
				'field_fid' => 'languages',
				'field_rows' => 0,
				'field_value' => '',
				'field_section' => 1,
				'field_order' => 4,
				'field_label_page' => 'Languages'
			);
			$this->char->add_bio_field($langValues);
			
			// grab the ID of the new field
			$langID = $this->db->insert_id();
			
			// create an empty users array
			$users = array();
			
			foreach ($query->result() as $r)
			{
				if ( ! empty($r->email))
				{
					// build the array with user information
					$users[$r->email] = array(
						'name'				=> $r->realName,
						'email'				=> $r->email,
						'join_date'			=> false,
						'leave_date'		=> $r->leaveDate,
						'status'			=> ($r->crewType != 'active' and $r->crewType != 'pending') ? 'inactive' : $r->crewType,
						'password_reset'	=> 1,
						'access_role'		=> 4,
					);
					
					if ( ! isset($users[$r->email]['main_char']))
					{
						// if we haven't set the main charcter yet, set it now
						$users[$r->email]['main_char'] = $r->crewid;
					}
					else
					{
						if ($r->crewType == 'active')
						{
							// if the main character has been set but the current character is active, use that
							$users[$r->email]['main_char'] = $c->crewid;
						}
					}
					
					if ( ! isset($users['last_post']))
					{
						// drop the latest post date in if it isn't set
						$users[$r->email]['last_post'] = $r->lastPost;
					}
					else
					{
						if ($r->crewType == 'active')
						{
							// if the latest post is set, but the current character is active, use that
							$users[$r->email]['last_post'] = $r->lastPost;
						}
					}
					
					if ($users[$r->email]['join_date'] === false)
					{
						// if the join date isn't set yet, set it
						$users[$r->email]['join_date'] = $r->joinDate;
					}
				}
			}
			
			// create an empty array for checking users
			$saved = array();
			
			foreach ($users as $u)
			{
				// create the user
				$useraction = $this->user->create_user($u);
				
				// get the user ID
				$userID = $this->db->insert_id();
				
				// store whether or not the save worked
				$saved['users'][] = ($useraction > 0) ? true : false;
				
				// create the user prefs
				$this->user->create_user_prefs($userID);
				
				// keeping track of user ids
				$charIDs[$u['email']] = $userID;
			}
			
			// optimize the table
			$this->dbutil->optimize_table('users');
			
			// pause the script
			sleep(1);
			
			foreach ($query->result() as $c)
			{
				// make sure the fields array is empty
				$fields = false;
				
				$charValues = array(
					'charid' => $c->crewid,
					'user' => ( ! empty($c->email)) ? $charIDs[$c->email] : null,
					'first_name' => $c->firstName,
					'middle_name' => $c->middleName,
					'last_name' => $c->lastName,
					'crew_type' => ($c->crewType == 'npc') ? 'active' : $c->crewType,
					'date_activate' => $c->joinDate,
					'date_deactivate' => $c->leaveDate,
					'rank' => $c->rankid,
					'position_1' => $c->positionid,
					'position_2' => $c->positionid2,
					'last_post' => $c->lastPost,
					'images' => $c->image,
				);
				$characteraction = $this->char->create_character($charValues);
				
				// store whether or not the save worked
				$saved['characters'][] = ($characteraction > 0) ? true : false;
				
				// create the array that stores all the character information
				$fields = array(
					1 	=> $c->gender,
					2 	=> $c->species,
					3 	=> $c->age,
					4 	=> $c->heightFeet."' ".$c->heightInches.'"',
					5 	=> $c->weight.' lbs',
					6 	=> $c->hairColor,
					7 	=> $c->eyeColor,
					8 	=> $c->physicalDesc,
					9 	=> $c->spouse,
					10 	=> $c->children,
					11 	=> $c->father,
					12 	=> $c->mother,
					13 	=> $c->brothers."\r\n\r\n".$c->sisters,
					14 	=> $c->otherFamily,
					15 	=> $c->personalityOverview,
					16 	=> $c->strengths,
					17 	=> $c->ambitions,
					18 	=> $c->hobbies,
					19 	=> $c->history,
					20 	=> $c->serviceRecord,
					$langID => $c->languages,
				);
				
				foreach ($fields as $field => $value)
				{
					$fieldValues = array(
						'data_field' => $field,
						'data_char' => $c->crewid,
						'data_user' => ( ! empty($c->email)) ? $charIDs[$c->email] : null,
						'data_value' => $value,
						'data_updated' => now()
					);
					$fieldata = $this->char->add_bio_field_data($fieldValues);
						
					// store whether or not the save worked
					$saved['formdata'][] = ($fieldata > 0) ? true : false;
				}
			}
			
			// set the count variables
			$count_users = (in_array(false, $saved['users'])) ? false : true;
			$count_characters = (in_array(false, $saved['characters'])) ? false : true;
			$count_formdata = (in_array(false, $saved['formdata'])) ? false : true;
			
			if ($count_users === true and $count_characters === true and $count_formdata === true)
			{
				$retval = array(
					'code' => 1,
					'message' => ''
				);
			}
			else
			{
				if ($count_users === false and $count_characters === true and $count_formdata === true)
				{
					$retval = array(
						'code' => 2,
						'message' => "Your characters and character data were upgraded, but not all users were upgraded"
					);
				}
				
				if ($count_users === false and $count_characters === false and $count_formdata === true)
				{
					$retval = array(
						'code' => 2,
						'message' => "Your character data was upgraded, but not all users or characters were upgraded"
					);
				}
				
				if ($count_users === true and $count_characters === false and $count_formdata === true)
				{
					$retval = array(
						'code' => 2,
						'message' => "Your users and character data was upgraded, but not all characters were upgraded"
					);
				}
				
				if ($count_users === true and $count_characters === true and $count_formdata === false)
				{
					$retval = array(
						'code' => 2,
						'message' => "Your users and characters were upgraded, but not all character data was upgraded"
					);
				}
				
				if ($count_users === false and $count_characters === true and $count_formdata === false)
				{
					$retval = array(
						'code' => 2,
						'message' => "Your characters were upgraded, but not all users or character data were upgraded"
					);
				}
				
				if ($count_users === true and $count_characters === false and $count_formdata === false)
				{
					$retval = array(
						'code' => 2,
						'message' => "Your users were upgraded, but not all characters or character data were upgraded"
					);
				}
				
				if ($count_users === false and $count_characters === false and $count_formdata === false)
				{
					$retval = array(
						'code' => 0,
						'message' => "Your users, characters and character data could not be updated"
					);
				}
			}
			
			$this->dbutil->optimize_table('characters');
			$this->dbutil->optimize_table('users');
			$this->dbutil->optimize_table('characters_data');
			$this->dbutil->optimize_table('characters_fields');
			$this->dbutil->optimize_table('user_prefs_values');
		} catch (Exception $e) {
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_final_password()
	{
		// grab the password
		$password = $_POST['password'];
		
		try {
			// hash the password
			$password = Auth::hash($password);
			
			// update everyone
			Jelly::query('user')->set(array('password' => $password))->update();
			
			// find out how many users don't have the right password
			$count = Jelly::query('user')->where('password', '!=', $password)->count();
			
			if ($count > 0)
			{
				$retval = array(
					'code' => 0,
					'message' => __("Not all of your users' passwords were updated")
				);
			}
			else
			{
				$retval = array(
					'code' => 1,
					'message' => ''
				);
			}
			
			// optmize the tables
			$this->dbutil->optimize_table('users');
		} catch (Exception $e) {
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_final_roles()
	{
		// grab the user IDs that should have the sys admin role
		$roles = $_POST['roles'];
		
		try {
			// temporary array
			$saved = array();
			
			foreach ($roles as $r)
			{
				$user = Jelly::factory('user', $r)->set(array('role' => 1))->save();
				$saved[] = $user->saved();
			}
			
			if ( ! in_array(true, $saved))
			{
				$retval = array(
					'code' => 0,
					'message' => __("None of your administrators were set")
				);
			}
			elseif (in_array(false, $saved) and in_array(true, $saved))
			{
				$retval = array(
					'code' => 0,
					'message' => __("Some of your administrators were set, but others were not")
				);
			}
			else
			{
				$retval = array(
					'code' => 1,
					'message' => ''
				);
			}
			
			// optmize the tables
			$this->dbutil->optimize_table('users');
		} catch (Exception $e) {
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_logs()
	{
		// get the number of logs in the sms table
		$count = $this->db->query("SELECT logid FROM sms_personallogs");
		$count_old = $count->num_rows();
		
		try {
			// drop the nova version of the table
			$this->dbforge->drop_table('personal_logs');
			
			// copy the sms version of the table along with all its data
			$this->db->query("CREATE TABLE ".$this->db->dbprefix."personal_logs SELECT * FROM sms_personallogs");
			
			// rename the fields to appropriate names
			$fields = array(
				'logid' => array(
					'name' => 'log_id',
					'type' => 'INT',
					'constraint' => 5),
				'logAuthor' => array(
					'name' => 'log_author_character',
					'type' => 'INT',
					'constraint' => 8),
				'logPosted' => array(
					'name' => 'log_date',
					'type' => 'BIGINT',
					'constraint' => 20),
				'logTitle' => array(
					'name' => 'log_title',
					'type' => 'VARCHAR',
					'constraint' => 255,
					'default' => 'upcoming'),
				'logContent' => array(
					'name' => 'log_content',
					'type' => 'TEXT'),
				'logStatus' => array(
					'name' => 'log_status',
					'type' => 'ENUM',
					'constraint' => "'activated','saved','pending'",
					'default' => 'activated'),
			);
			
			// do the modification
			$this->dbforge->modify_column('personal_logs', $fields);
			
			// add the other columns
			$add = array(
				'log_author_user' => array(
					'type' => 'INT',
					'constraint' => 8),
				'log_tags' => array(
					'type' => 'TEXT'),
				'log_last_update' => array(
					'type' => 'BIGINT',
					'constraint' => 20)
			);
			
			// do the modification
			$this->dbforge->add_column('personal_logs', $add);
			
			// make sure the auto increment and primary key are right
			$this->db->query("ALTER TABLE ".$this->db->dbprefix."personal_logs MODIFY COLUMN `log_id` INT(5) auto_increment primary key");
			
			// get the new count of logs
			$count_new = $this->db->count_all('personal_logs');
			
			if ($count_new == 0)
			{
				$retval = array(
					'code' => 0,
					'message' => "None of your personal logs were able to be upgraded"
				);
			}
			elseif ($count_new > 0 and $count_new != $count_old)
			{
				$retval = array(
					'code' => 2,
					'message' => "Some of your personal logs were upgraded, but some where unable to be upgraded"
				);
			}
			else
			{
				$retval = array(
					'code' => 1,
					'message' => ''
				);
			}
			
			$this->dbutil->optimize_table('personal_logs');
		} catch (Exception $e) {
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_missions()
	{
		// get the number of missions in the sms table
		$count = $this->db->query("SELECT missionid FROM sms_missions");
		$count_missions_old = $count->num_rows();
		
		// get the number of mission posts in the sms table
		$count = $this->db->query("SELECT postid FROM sms_posts");
		$count_posts_old = $count->num_rows();
		
		try {
			// drop the nova version of the table
			$this->dbforge->drop_table('missions');
			
			// copy the sms version of the table along with all its data
			$this->db->query("CREATE TABLE ".$this->db->dbprefix."missions SELECT * FROM sms_missions");
			
			// rename the fields to appropriate names
			$fields = array(
				'missionid' => array(
					'name' => 'mission_id',
					'type' => 'INT',
					'constraint' => 8),
				'missionOrder' => array(
					'name' => 'mission_order',
					'type' => 'INT',
					'constraint' => 5),
				'missionTitle' => array(
					'name' => 'mission_title',
					'type' => 'VARCHAR',
					'constraint' => 255),
				'missionImage' => array(
					'name' => 'mission_images',
					'type' => 'TEXT'),
				'missionStatus' => array(
					'name' => 'mission_status',
					'type' => 'ENUM',
					'constraint' => "'upcoming','current','completed'",
					'default' => 'upcoming'),
				'missionStart' => array(
					'name' => 'mission_start',
					'type' => 'BIGINT',
					'constraint' => 20),
				'missionEnd' => array(
					'name' => 'mission_end',
					'type' => 'BIGINT',
					'constraint' => 20),
				'missionDesc' => array(
					'name' => 'mission_desc',
					'type' => 'TEXT'),
				'missionSummary' => array(
					'name' => 'mission_summary',
					'type' => 'TEXT'),
				'missionNotes' => array(
					'name' => 'mission_notes',
					'type' => 'TEXT'),
			);
			
			// do the modification
			$this->dbforge->modify_column('missions', $fields);
			
			// add the other fields
			$add = array(
				'mission_notes_updated' => array(
					'type' => 'BIGINT',
					'constraint' => 20),
				'mission_group' => array(
					'type' => 'INT',
					'constraint' => 5)
			);
			
			// do the modifications
			$this->dbforge->add_column('missions', $add);
			
			// make sure the auto increment and primary key are right
			$this->db->query("ALTER TABLE ".$this->db->dbprefix."missions MODIFY COLUMN `mission_id` INT(8) auto_increment primary key");
			
			// drop the nova version of the table
			$this->dbforge->drop_table('posts');
			
			// copy the sms version of the table along with all its data
			$this->db->query("CREATE TABLE ".$this->db->dbprefix."posts SELECT * FROM sms_posts");
			
			// rename the fields to appropriate names
			$fields = array(
				'postid' => array(
					'name' => 'post_id',
					'type' => 'INT',
					'constraint' => 8),
				'postAuthor' => array(
					'name' => 'post_authors',
					'type' => 'TEXT'),
				'postPosted' => array(
					'name' => 'post_date',
					'type' => 'BIGINT',
					'constraint' => 20),
				'postTitle' => array(
					'name' => 'post_title',
					'type' => 'VARCHAR',
					'constraint' => 255,
					'default' => ''),
				'postContent' => array(
					'name' => 'post_content',
					'type' => 'TEXT'),
				'postStatus' => array(
					'name' => 'post_status',
					'type' => 'ENUM',
					'constraint' => "'activated','saved','pending'",
					'default' => 'activated'),
				'postLocation' => array(
					'name' => 'post_location',
					'type' => 'VARCHAR',
					'constraint' => 255,
					'default' => ''),
				'postTimeline' => array(
					'name' => 'post_timeline',
					'type' => 'VARCHAR',
					'constraint' => 255,
					'default' => ''),
				'postMission' => array(
					'name' => 'post_mission',
					'type' => 'INT',
					'constraint' => 8),
				'postSave' => array(
					'name' => 'post_saved',
					'type' => 'INT',
					'constraint' => 11),
			);
			
			// do the modifications
			$this->dbforge->modify_column('posts', $fields);
			
			// add the other fields
			$add = array(
				'post_authors_users' => array(
					'type' => 'TEXT'),
				'post_tags' => array(
					'type' => 'TEXT'),
				'post_last_update' => array(
					'type' => 'BIGINT',
					'constraint' => 20)
			);
			
			// do the modifications
			$this->dbforge->add_column('posts', $add);
			
			// remove the tag column
			$this->dbforge->drop_column('posts', 'postTag');
			
			// make sure the auto increment and primary key are correct
			$this->db->query("ALTER TABLE ".$this->db->dbprefix."posts MODIFY COLUMN `post_id` INT(8) auto_increment primary key");
			
			// count the missions
			$count_missions_new = $this->db->count_all('missions');
			
			// count the posts
			$count_posts_new = $this->db->count_all('posts');
			
			if ($count_missions_new == 0 and $count_posts_new == 0)
			{
				$retval = array(
					'code' => 0,
					'message' => "None of your missions or mission posts were able to be upgraded"
				);
			}
			elseif ($count_missions_new > 0 and $count_posts_new == 0)
			{
				if ($count_missions_new != $count_missions_old)
				{
					$retval = array(
						'code' => 2,
						'message' => $count_missions_new." of ".$count_missions_old." missions were upgraded, but your mission posts were not"
					);
				}
				else
				{
					$retval = array(
						'code' => 2,
						'message' => "Your missions were upgraded, but your mission posts were not"
					);
				}
			}
			elseif ($count_missions_new == 0 and $count_posts_new > 0)
			{
				if ($count_posts_new != $count_posts_old)
				{
					$retval = array(
						'code' => 2,
						'message' => $count_posts_new." of ".$count_posts_old." mission posts were upgraded, but your missions were not"
					);
				}
				else
				{
					$retval = array(
						'code' => 2,
						'message' => "Your mission posts were upgraded, but your missions were not"
					);
				}
			}
			else
			{
				if ($count_missions_new == $count_missions_old and $count_posts_new == $count_posts_old)
				{
					$retval = array(
						'code' => 1,
						'message' => ''
					);
				}
				elseif ($count_missions_new == $count_missions_old and $count_posts_new != $count_posts_old)
				{
					$retval = array(
						'code' => 2,
						'message' => "All of your missions and ".$count_posts_new." of ".$count_posts_old." mission posts were upgraded"
					);
				}
				elseif ($count_missions_new != $count_missions_old and $count_posts_new == $count_posts_old)
				{
					$retval = array(
						'code' => 2,
						'message' => "All of your mission posts and ".$count_missions_new." of ".$count_missions_old." missions were upgraded"
					);
				}
				elseif ($count_missions_new != $count_missions_old and $count_posts_new != $count_posts_old)
				{
					$retval = array(
						'code' => 2,
						'message' => $count_missions_new." of ".$count_missions_old." missions and ".$count_posts_new." of ".$count_posts_old." mission posts were upgraded"
					);
				}
			}
			
			$this->dbutil->optimize_table('missions');
			$this->dbutil->optimize_table('posts');
		} catch (Exception $e) {
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_news()
	{
		// get the number of news items in the sms table
		$count = $this->db->query("SELECT newsid FROM sms_news");
		$count_news_old = $count->num_rows();
		
		// get the number of news categories in the sms table
		$count = $this->db->query("SELECT catid FROM sms_news_categories");
		$count_cats_old = $count->num_rows();
		
		try {
			// drop the nova versions of the tables
			$this->dbforge->drop_table('news');
			$this->dbforge->drop_table('news_categories');
			
			// copy the sms version of the table along with all its data
			$this->db->query("CREATE TABLE ".$this->db->dbprefix."news_categories SELECT * FROM sms_news_categories");
			
			// rename the fields to appropriate names
			$fields = array(
				'catid' => array(
					'name' => 'newscat_id',
					'type' => 'INT',
					'constraint' => 5),
				'catName' => array(
					'name' => 'newscat_name',
					'type' => 'VARCHAR',
					'constraint' => 255),
				'catVisible' => array(
					'name' => 'newscat_display',
					'type' => 'ENUM',
					'constraint' => "'y','n'",
					'default' => 'y'),
			);
			
			// do the modifications
			$this->dbforge->modify_column('news_categories', $fields);
			
			// remove the user level column
			$this->dbforge->drop_column('news_categories', 'catUserLevel');
			
			// make sure the auto increment and primary id are correct
			$this->db->query("ALTER TABLE ".$this->db->dbprefix."news_categories MODIFY COLUMN `newscat_id` INT(5) auto_increment primary key");
			
			// copy the sms version of the table along with all its data
			$this->db->query("CREATE TABLE ".$this->db->dbprefix."news SELECT * FROM sms_news");
			
			// rename the fields to appropriate names
			$fields = array(
				'newsid' => array(
					'name' => 'news_id',
					'type' => 'INT',
					'constraint' => 8),
				'newsCat' => array(
					'name' => 'news_cat',
					'type' => 'INT',
					'constraint' => 3),
				'newsAuthor' => array(
					'name' => 'news_author_character',
					'type' => 'INT',
					'constraint' => 8),
				'newsPosted' => array(
					'name' => 'news_date',
					'type' => 'BIGINT',
					'constraint' => 20),
				'newsTitle' => array(
					'name' => 'news_title',
					'type' => 'VARCHAR',
					'constraint' => 255,
					'default' => 'upcoming'),
				'newsContent' => array(
					'name' => 'news_content',
					'type' => 'TEXT'),
				'newsStatus' => array(
					'name' => 'news_status',
					'type' => 'ENUM',
					'constraint' => "'activated','saved','pending'",
					'default' => 'activated'),
				'newsPrivate' => array(
					'name' => 'news_private',
					'type' => 'ENUM',
					'constraint' => "'y','n'",
					'default' => 'n'),
			);
			
			// do the modifications
			$this->dbforge->modify_column('news', $fields);
			
			// add the missing columns
			$add = array(
				'news_author_user' => array(
					'type' => 'INT',
					'constraint' => 8),
				'news_tags' => array(
					'type' => 'TEXT'),
				'news_last_update' => array(
					'type' => 'BIGINT',
					'constraint' => 20)
			);
			
			// do the modifications
			$this->dbforge->add_column('news', $add);
			
			// make sure the auto increment and primary key are right
			$this->db->query("ALTER TABLE ".$this->db->dbprefix."news MODIFY COLUMN `news_id` INT(8) auto_increment primary key");
			
			// count the news items
			$count_news_new = $this->db->count_all('news');
			
			// count the news categories
			$count_cats_new = $this->db->count_all('news_categories');
			
			if ($count_news_new == 0 and $count_cats_new == 0)
			{
				$retval = array(
					'code' => 0,
					'message' => "None of your news categories or news item were able to be upgraded"
				);
			}
			elseif ($count_news_new > 0 and $count_cats_new == 0)
			{
				if ($count_news_new != $count_news_old)
				{
					$retval = array(
						'code' => 2,
						'message' => $count_news_new." of ".$count_news_old." news items were upgraded, but your news categories were not"
					);
				}
				else
				{
					$retval = array(
						'code' => 2,
						'message' => "Your news items were upgraded, but your news categories were not"
					);
				}
			}
			elseif ($count_news_new == 0 and $count_cats_new > 0)
			{
				if ($count_cats_new != $count_cats_old)
				{
					$retval = array(
						'code' => 2,
						'message' => $count_cats_new." of ".$count_cats_old." news categories were upgraded, but your news items were not"
					);
				}
				else
				{
					$retval = array(
						'code' => 2,
						'message' => "Your news categories were upgraded, but your news items were not"
					);
				}
			}
			else
			{
				if ($count_news_new == $count_news_old and $count_cats_new == $count_cats_old)
				{
					$retval = array(
						'code' => 1,
						'message' => ''
					);
				}
				elseif ($count_news_new == $count_news_old and $count_cats_new != $count_cats_old)
				{
					$retval = array(
						'code' => 2,
						'message' => "All of your news items and ".$count_cats_new." of ".$count_cats_old." news categories were upgraded"
					);
				}
				elseif ($count_news_new != $count_news_old and $count_cats_new == $count_cats_old)
				{
					$retval = array(
						'code' => 2,
						'message' => "All of your news categories and ".$count_news_new." of ".$count_news_old." news items were upgraded"
					);
				}
				elseif ($count_news_new != $count_news_old and $count_cats_new != $count_cats_old)
				{
					$retval = array(
						'code' => 2,
						'message' => $count_news_new." of ".$count_news_old." news items and ".$count_cats_new." of ".$count_cats_old." news categories were upgraded"
					);
				}
			}
			
			$this->dbutil->optimize_table('news');
			$this->dbutil->optimize_table('news_categories');
		} catch (Exception $e) {
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_quick_install()
	{
		try {
			// do the quick installs
			Utility::install_rank();
			Utility::install_skin();
			
			// get the directory listing for the genre
			$dir = Utility::directory_map(APPPATH.'assets/common/'.Kohana::config('nova.genre').'/ranks/', true);
			
			// set the items to be pulled out of the listing
			$pop = array('index.html');
			
			// remove unwanted items
			foreach ($pop as $value)
			{
				// find the item in the directory listing
				$key = array_search($value, $dir);
				
				if ($key !== false)
				{
					unset($dir[$key]);
				}
			}
			
			// get the count of ranks
			$dir_ranks = count($dir);
			
			// pause the script for 1 second
			sleep(1);
			
			// reset the variables
			$pop = null;
			$dir = null;
			
			// get the listing of the directory
			$dir = Utility::directory_map(APPPATH.'views/', true);
			
			// create an array of items to remove
			$pop = array('index.html');
			
			# TODO: remove this after the application directory has been cleaned out
			$pop[] = '_base';
			$pop[] = 'template.php';
			
			// remove the items
			foreach ($pop as $value)
			{
				// find the location in the directory listing
				$key = array_search($value, $dir);
				
				if ($key !== false)
				{
					unset($dir[$key]);
				}
			}
			
			// get the count of skins
			$dir_skins = count($dir);
			
			// get the catalogue count for ranks
			$db_ranks = Jelly::query('cataloguerank')->count();
			
			// get the catalogue count for skins
			$db_skins = Jelly::query('catalogueskin')->count();

			if ($dir_ranks == $db_ranks and $dir_skins == $db_skins)
			{
				$retval = array(
					'code' => 1,
					'message' => ''
				);
			}
			elseif ($dir_ranks != $db_ranks and $dir_skins == $db_skins)
			{
				$retval = array(
					'code' => 2,
					'message' => __("Your skins were installed but not all of your rank sets were installed. Please try to install your ranks sets manually from the rank catalogue page.")
				);
			}
			elseif ($dir_ranks == $db_ranks and $dir_skins != $db_skins)
			{
				$retval = array(
					'code' => 2,
					'message' => __("Your rank sets were installed but not all of your skins were installed. Please try to install your skins manually from the skin catalogue page.")
				);
			}
			elseif ($dir_ranks != $db_ranks and $dir_skins != $db_skins)
			{
				$retval = array(
					'code' => 0,
					'message' => __("Additional ranks and skins were not installed. Please try to do so manually from the catalogue pages.")
				);
			}
			
			// optmize the tables
			$this->dbutil->optimize_table('catalogue_ranks');
			$this->dbutil->optimize_table('catalogue_skins');
			$this->dbutil->optimize_table('catalogue_skinsecs');
		} catch (Exception $e) {
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_settings()
	{
		$this->load->model('settings_model', 'settings');
		$this->load->model('messages_model', 'msgs');
		
		// figure out what the name of the table is
		$sql = ($this->db->table_exists('sms_settings'))
			? "SELECT * FROM sms_settings WHERE globalid = 1"
			: "SELECT * FROM sms_globals WHERE globalid = 1";
		
		$query = $this->db->query($sql);
		
		// create arrays for checking to see if everything was saved
		$settings = array();
		$messages = array();
		
		foreach ($query->result() as $r)
		{
			$value = array('setting_value' => $r->shipPrefix.' '.$r->shipName.' '.$r->shipRegistry);
			$settings[] = $this->settings->update_setting('sim_name', $value);
			
			$value = array('setting_value' => $r->simmYear);
			$settings[] = $this->settings->update_setting('sim_year', $value);
			
			$value = array('setting_value' => ($r->jpCount == 'y') ? 'multiple' : 'single');
			$settings[] = $this->settings->update_setting('post_count', $value);
			
			$value = array('setting_value' => $r->emailSubject);
			$settings[] = $this->settings->update_setting('email_subject', $value);
		}
		
		// get the messages
		$query = $this->db->query("SELECT * FROM sms_messages WHERE messageid = 1");
		
		foreach ($query->result() as $r)
		{
			$value = array('message_content' => $r->welcomeMessage);
			$messages[] = $this->msgs->update_message($value, 'welcome_msg');
			
			$value = array('message_content' => $r->simmMessage);
			$messages[] = $this->msgs->update_message($value, 'sim');
			
			$value = array('message_content' => $r->joinDisclaimer);
			$messages[] = $this->msgs->update_message($value, 'join_disclaimer');
			
			$value = array('message_content' => $r->acceptMessage);
			$messages[] = $this->msgs->update_message($value, 'accept_message');
			
			$value = array('message_content' => $r->rejectMessage);
			$messages[] = $this->msgs->update_message($value, 'reject_message');
			
			$value = array('message_content' => $r->samplePostQuestion);
			$messages[] = $this->msgs->update_message($value, 'join_post');
		}
		
		// optmize the tables
		$this->dbutil->optimize_table('settings');
		$this->dbutil->optimize_table('messages');
		
		// check to see if everything worked
		$settings_count = (in_array(false, $settings)) ? false : true;
		$messages_count = (in_array(false, $messages)) ? false : true;
		
		if ($settings_count === true and $messages_count === true)
		{
			$retval = array(
				'code' => 1,
				'message' => ''
			);
		}
		else
		{
			if ($settings_count === true and $messages_count === false)
			{
				$retval['code'] = 2;
				$retval['message'] = "All of your settings were upgraded, but some of your messages couldn't be upgraded";
			}
			
			if ($settings_count === false and $messages_count === true)
			{
				$retval['code'] = 2;
				$retval['message'] = "All of your messages were upgraded, but some of your settings couldn't be upgraded";
			}
			
			if ($settings_count === false and $messages_count === false)
			{
				$retval['code'] = 0;
				$retval['message'] = "None of your settings or messages could be upgraded";
			}
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_specs()
	{
		try {
			// get the specs from the sms table
			$result = $this->db->query(Database::SELECT, 'SELECT * FROM sms_specs WHERE specid = 1', true);
			
			// create the spec item
			Jelly::factory('spec')
				->set(array(
					'name' => Jelly::query('setting', 'sim_name')->limit(1)->select()->value,
					'order' => 0,
				))
				->save();
			
			// create an empty array for validating the specs upgrade
			$specs = array();
			
			foreach ($result as $r)
			{
				// ship class
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 23,
						'value' => $r->shipClass,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// ship role
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 24,
						'value' => $r->shipRole,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// duration
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 25,
						'value' => $r->duration,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// refit
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 26,
						'value' => $r->refit.' '.$r->refitUnit,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// resupply
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 27,
						'value' => $r->resupply.' '.$r->resupplyUnit,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// length
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 28,
						'value' => $r->length,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// width
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 29,
						'value' => $r->width,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// height
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 30,
						'value' => $r->height,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// decks
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 31,
						'value' => $r->decks,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// officers
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 32,
						'value' => $r->complimentOfficers,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// enlisted
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 33,
						'value' => $r->complimentEnlisted,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// marines
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 34,
						'value' => $r->complimentMarines,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// civilians
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 35,
						'value' => $r->complimentCivilians,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// emergency compliment
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 36,
						'value' => $r->complimentEmergency,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// warp cruise
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 37,
						'value' => $r->warpCruise,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// warp max cruise
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 38,
						'value' => $r->warpMaxCruise.' '.$r->warpMaxTime,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// warp emergency
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 39,
						'value' => $r->warpEmergency.' '.$r->warpEmergencyTime,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// defensive
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 40,
						'value' => $r->shields."\r\n\r\n".$r->defensive,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// weapons
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 41,
						'value' => $r->phasers."\r\n\r\n".$r->torpedoLaunchers,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// armament
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 42,
						'value' => $r->torpedoCompliment,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// number of shuttlebays
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 43,
						'value' => $r->shuttlebays,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// shuttles
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 44,
						'value' => $r->shuttles,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// number of fighters
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 45,
						'value' => $r->fighters,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
				
				// number of runabouts
				$item = Jelly::factory('formdata')
					->set(array(
						'field' => 46,
						'value' => $r->runabouts,
						'item' => 1,
						'form' => 'specs'
					))
					->save();
				$specs[] = $item->saved();
			}
			
			if (in_array(false, $specs) and ! in_array(true, $specs))
			{
				$retval = array(
					'code' => 0,
					'message' => __("Your specifications were not upgraded")
				);
			}
			elseif (in_array(false, $specs) and in_array(true, $specs))
			{
				$retval = array(
					'code' => 2,
					'message' => __("Some of your specifications were upgraded, but others were not")
				);
			}
			else
			{
				$retval = array(
					'code' => 1,
					'message' => ''
				);
			}
			
			// optmize the tables
			$this->dbutil->optimize_table('specs');
			$this->dbutil->optimize_table('forms_data');
		} catch (Exception $e) {
			// catch the exception
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_tour()
	{
		try {
			// get the tour items
			$result = $this->db->query(Database::SELECT, 'SELECT * FROM sms_tour', true);
			
			// create an array for validating
			$tour = array();
			
			foreach ($result as $r)
			{
				$images = array();
				
				if ( ! empty($r->tourPicture1))
				{
					$images[] = $r->tourPicture1;
				}
				
				if ( ! empty($r->tourPicture2))
				{
					$images[] = $r->tourPicture2;
				}
				
				if ( ! empty($r->tourPicture3))
				{
					$images[] = $r->tourPicture3;
				}
				
				// make the images array a string
				$images = implode(',', $images);
				
				$item = Jelly::factory('tour')
					->set(array(
						'name' => $r->tourName,
						'order' => $r->tourOrder,
						'display' => $r->tourDisplay,
						'summary' => $r->tourSummary,
						'images' => $images,
						'specitem' => 1
					))
					->save();
				$tour[] = $item->saved();
				
				$dataitem = Jelly::factory('formdata')
					->set(array(
						'field' => 47,
						'value' => $r->tourLocation,
						'item' => $item->id(),
						'form' => 'tour'
					))
					->save();
				$tour[] = $dataitem->saved();
				
				$dataitem = Jelly::factory('formdata')
					->set(array(
						'field' => 48,
						'value' => $r->tourDesc,
						'item' => $item->id(),
						'form' => 'tour'
					))
					->save();
				$tour[] = $dataitem->saved();
			}
			
			if (in_array(false, $tour) and ! in_array(true, $tour))
			{
				$retval = array(
					'code' => 0,
					'message' => __("Your tour items were not upgraded")
				);
			}
			elseif (in_array(false, $tour) and in_array(true, $tour))
			{
				$retval = array(
					'code' => 2,
					'message' => __("Some of your tour items were upgraded, but others were not")
				);
			}
			else
			{
				$retval = array(
					'code' => 1,
					'message' => ''
				);
			}
			
			// optmize the tables
			$this->dbutil->optimize_table('tour');
			$this->dbutil->optimize_table('forms_data');
		} catch (Exception $e) {
			// catch the exception
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_user_awards()
	{
		// change the awards received model to prevent null values
		Jelly::meta('awardrec')->field('date')->auto_now_create = false;
		
		try {
			// get the crew from the sms table
			$result = $this->db->query(Database::SELECT, 'SELECT * FROM sms_crew', true);
			
			// create an array for saved entries
			$saved = array();
			
			foreach ($result as $c)
			{
				$user = Jelly::query('character', $c->crewid)->select()->user;
				
				if ( ! empty($c->awards))
				{
					$awards = explode(';', $c->awards);
					
					foreach ($awards as $a)
					{
						if (strstr($a, '|') !== false)
						{
							$x = explode('|', $a);
							
							$awardaction = Jelly::factory('awardrec')
								->set(array(
									'character' => $c->crewid,
									'user' => $user->id,
									'award' => $x[0],
									'date' => $x[1],
									'reason' => $x[2]
								))
								->save();
							$saved[] = $awardaction->saved();
						}
						else
						{
							$awardaction = Jelly::factory('awardrec')
								->set(array(
									'character' => $c->crewid,
									'user' => $user->id,
									'award' => $a,
									'date' => null
								))
								->save();
							$saved[] = $awardaction->saved();
						}
					}
				}
			}
			
			if ( ! in_array(true, $saved))
			{
				$retval = array(
					'code' => 0,
					'message' => __("Your given awards could not be upgraded")
				);
			}
			elseif (in_array(true, $saved) and in_array(false, $saved))
			{
				$retval = array(
					'code' => 2,
					'message' => __("All of your given awards could not be upgraded")
				);
			}
			else
			{
				$retval = array(
					'code' => 1,
					'message' => ''
				);
			}
			
			// optmize the tables
			$this->dbutil->optimize_table('awards_received');
		} catch (Exception $e) {
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_user_defaults()
	{
		try {
			// get the total number of users
			$users = Jelly::query('user')->count();
			
			// get the total number of characters
			$characters = Jelly::query('character')->count();
			
			if ($users > 0 and $characters > 0)
			{
				// pull the defaults for skins and ranks
				$defaults = array(
					'skin_main'		=> Jelly::query('catalogueskinsec')->defaultskin('main')->select()->skin->location,
					'skin_admin'	=> Jelly::query('catalogueskinsec')->defaultskin('admin')->select()->skin->location,
					'skin_wiki'		=> Jelly::query('catalogueskinsec')->defaultskin('wiki')->select()->skin->location,
					'rank'			=> Jelly::query('cataloguerank')->defaultrank()->select()->location,
					'links'			=> '',
					'language'		=> 'en-us',
					'dst'			=> 0,
				);
				
				// update all users
				Jelly::query('user')->set($defaults)->update();
				
				$retval = array(
					'code' => 1,
					'message' => ''
				);
			}
			else
			{
				$retval = array(
					'code' => 0,
					'message' => __("User defaults could not be upgraded")
				);
			}
			
			// optmize the tables
			$this->dbutil->optimize_table('characters');
			$this->dbutil->optimize_table('users');
		} catch (Exception $e) {
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_user_logs()
	{
		try {
			// get the crew from the sms table
			$result = $this->db->query(Database::SELECT, 'SELECT * FROM sms_crew', true);
			
			foreach ($result as $c)
			{
				$user = Jelly::query('character', $c->crewid)->select()->user;
				
				if ( ! is_null($user) and $user->id > 0)
				{
					// update the personal logs
					$logs = Jelly::query('personallog')
						->where('author_character', '=', $c->crewid)
						->set(array('author_user' => $user->id))
						->update();
				}
			}
			
			// count the number of personal logs that don't have a user (there shouldn't be any)
			$blank = Jelly::query('personallog')->where('author_user', '=', '')->count();
			
			if ($blank > 0)
			{
				$retval = array(
					'code' => 0,
					'message' => __("Some of your personal logs could not be upgraded and as a result, may not be associated with some users properly")
				);
			}
			else
			{
				$retval = array(
					'code' => 1,
					'message' => ''
				);
			}
			
			// optmize the tables
			$this->dbutil->optimize_table('personal_logs');
		} catch (Exception $e) {
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_user_news()
	{
		try {
			// get the crew from the sms table
			$result = $this->db->query(Database::SELECT, 'SELECT * FROM sms_crew', true);
			
			foreach ($result as $c)
			{
				$user = Jelly::query('character', $c->crewid)->select()->user;
				
				if ( ! is_null($user) and $user->id > 0)
				{
					// update the news items
					$news = Jelly::query('news')
						->where('author_character', '=', $c->crewid)
						->set(array('author_user' => $user->id))
						->update();
				}
			}
			
			// count the number of news items without a user (there shouldn't be any)
			$blank = Jelly::query('news')->where('author_user', '=', '')->count();
			
			if ($blank > 0)
			{
				$retval = array(
					'code' => 0,
					'message' => __("Some of your news items could not be upgraded and as a result, may not be associated with some users properly")
				);
			}
			else
			{
				$retval = array(
					'code' => 1,
					'message' => ''
				);
			}
			
			// optmize the tables
			$this->dbutil->optimize_table('news');
		} catch (Exception $e) {
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_user_posts()
	{
		try {
			// get all the posts
			$posts = Jelly::query('post')->select();
			
			// set a temp array to collect saves
			$saved = array();
			
			foreach ($posts as $p)
			{
				// grab the authors and put them into an array
				$authors = explode(',', $p->authors);
				
				// make sure we have an array
				$array = array();
				
				foreach ($authors as $a)
				{
					if ($a > 0)
					{
						// get the user id
						$user = Jelly::query('character', $a)->select()->user;
					
						if ( ! is_null($user) and ! in_array($user->id, $array))
						{
							$array[] = $user->id;
						}
					}
				}
				
				// create a string from the array
				$users = implode(',', $array);
				
				// update the post
				$post = Jelly::factory('post', $p->id)
					->set(array('author_users' => $users))
					->save();
				$saved[] = $post->saved();
			}
			
			if ( ! in_array(false, $saved))
			{
				$retval = array(
					'code' => 1,
					'message' => ''
				);
			}
			else
			{
				$retval = array(
					'code' => 0,
					'message' => __("Not all of your mission posts could be upgraded")
				);
			}
			
			// optmize the tables
			$this->dbutil->optimize_table('posts');
		} catch (Exception $e) {
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
	
	public function upgrade_welcome()
	{
		try {
			// update the welcome page header
			$msg = Jelly::query('message', 'welcome_head')->limit(1)->select();
			$msg->value = 'Welcome to the '.Jelly::query('setting', 'sim_name')->limit(1)->select()->value.'!';
			$msg->save();
			
			if ($msg->saved())
			{
				$retval = array(
					'code' => 1,
					'message' => ''
				);
			}
			else
			{
				$retval = array(
					'code' => 0,
					'message' => __("Your welcome message couldn't be upgraded, please do so manually")
				);
			}
			
			// optmize the tables
			$this->dbutil->optimize_table('messages');
		} catch (Exception $e) {
			$retval = array(
				'code' => 0,
				'message' => 'ERROR: '.$e->getMessage().' - line '.$e->getLine().' of '.$e->getFile()
			);
		}
		
		echo json_encode($retval);
	}
}