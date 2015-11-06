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
	/** @var \phpbb\cache\service */
	protected $cache;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/**
	* Constructor
	* @param \phpbb\cache\service				$cache
	* @param \phpbb\user						$user
	* @param \phpbb\db\driver\driver_interface	$db
	*
	*/
	public function __construct(\phpbb\cache\service $cache, \phpbb\user $user, \phpbb\db\driver\driver_interface $db)
	{
		$this->cache = $cache;	
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
			'core.viewtopic_before_f_read_check'		=> 'build_group_name_cache',
			'core.viewtopic_modify_post_row'			=> 'viewtopic_modify_post_row',
			'core.viewtopic_cache_user_data'			=> 'viewtopic_cache_user_data',
			'core.viewtopic_cache_guest_data'			=> 'viewtopic_cache_guest_data',
		);
	}

	/**
	* Build a cache of group names
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function build_group_name_cache($event)
	{
		if (($this->cache->get('_user_group_names')) === false)
		{
			$sql = 'SELECT group_id, group_name, group_type
				FROM ' . GROUPS_TABLE . '
				WHERE group_type <> ' . GROUP_HIDDEN;
			$result = $this->db->sql_query($sql);
			$user_group_names = array();

			while ($row = $this->db->sql_fetchrow($result))
			{
				$user_group_names[$row['group_id']] = ($row['group_type'] == GROUP_SPECIAL) ? $this->user->lang['G_' . $row['group_name']] : $row['group_name'];
			}
			$this->db->sql_freeresult($result);

			// cache this data for 5 minutes
			$this->cache->put('_user_group_names', $user_group_names, 300);
		}
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
		$user_cache = $event['user_poster_data'];
		$groups_name = $this->cache->get('_user_group_names');
		$group_id = array();
		foreach ($groups_name as $key => $value)
		{
			$group_ids[] = $key;
		}
		if (sizeof($groups_name) && in_array($user_cache['group_id'], $group_ids))
		{
			$event['post_row'] = array_merge($event['post_row'],array(
				'POSTER_GROUP'		=> $groups_name[$user_cache['group_id']],
			));
		}
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