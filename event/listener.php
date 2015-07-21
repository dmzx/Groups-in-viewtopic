<?php
/**
*
* @package phpBB Extension - Groups in viewtopic
* @copyright (c) 2015 dmzx - http://www.dmzx-web.net
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace dmzx\groupsintopic\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/**
	* Constructor
	* @param \phpbb\user						$user
	* @param \phpbb\db\driver\driver_interface	$db
	*
	*/
	public function __construct(\phpbb\user $user, \phpbb\db\driver\driver_interface $db)
	{
		$this->user = $user;
		$this->db = $db;
	}

	/**
	* Assign functions defined in this class to event listeners in the core
	*
	* @return array
	* @static
	* @access public
	*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.viewtopic_modify_post_row'			=> 'viewtopic_modify_post_row',
			'core.viewtopic_cache_user_data'			=> 'viewtopic_cache_user_data',
			'core.viewtopic_cache_guest_data'			=> 'viewtopic_cache_guest_data',
		);
	}

	/**
	* Modify the viewtopic post row
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function viewtopic_modify_post_row($event)
	{
		$row = $event['row'];
		$user_cache = $event['user_poster_data'];

		// Groups in viewtopic
		$sql = 'SELECT group_id, group_name, group_type
		FROM ' . GROUPS_TABLE . '
		WHERE group_type <> ' . GROUP_HIDDEN;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
		$groups_name[$row['group_id']] = ($row['group_type'] == GROUP_SPECIAL) ? $this->user->lang['G_' . $row['group_name']] : $row['group_name'];
		}
		$this->db->sql_freeresult($result);

		$event['post_row'] = array_merge($event['post_row'],array(
			'POSTER_GROUP'		=> $groups_name[$user_cache['group_id']],
		));
	}

	/**
	* Update viewtopic user data
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function viewtopic_cache_user_data($event)
	{
		$array = $event['user_cache_data'];
		$array['group_id'] = $event['row']['group_id'];
		$event['user_cache_data'] = $array;
	}

	/**
	* Update viewtopic guest data
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function viewtopic_cache_guest_data($event)
	{
		$array = $event['user_cache_data'];
		$array['group_id'] = '';
		$event['user_cache_data'] = $array;
	}
}