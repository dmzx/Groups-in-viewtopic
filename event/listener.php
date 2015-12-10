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
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\cache\service */
	protected $cache;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string phpBB root path */
	protected $root_path;

	/** @var string phpEx */
	protected $php_ext;

	/**
	* Constructor
	* @param \phpbb\auth\auth					$auth			Auth object
	* @param \phpbb\cache\service				$cache
	* @param \phpbb\user						$user
	* @param \phpbb\db\driver\driver_interface	$db
	*
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\cache\service $cache, \phpbb\user $user, \phpbb\db\driver\driver_interface $db, $root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->user = $user;
		$this->db = $db;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;		
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
		if (($this->cache->get('_user_groups')) === false)
		{
			$sql_ary = array(
				'SELECT'	=> 'ug.user_id, g.group_name, g.group_colour, g.group_type, g.group_id',
				'FROM'		=> array(
					USERS_TABLE => 'u',
				),
				'LEFT_JOIN'	=> array(
					array(
						'FROM'	=> array(USER_GROUP_TABLE => 'ug'),
						'ON'	=> 'ug.user_id = u.user_id',
					),
					array(
						'FROM'	=> array(GROUPS_TABLE => 'g'),
						'ON'	=> 'ug.group_id = g.group_id',
					),
				),
				'WHERE'		=> $this->db->sql_in_set('u.user_type', array(USER_FOUNDER, USER_NORMAL)) . ' AND ug.user_pending = 0',
				'ORDER_BY'	=> 'u.user_id ASC, g.group_name',
			);
			$result = $this->db->sql_query($this->db->sql_build_query('SELECT', $sql_ary));

			$user_groups = array();
			while ($row = $this->db->sql_fetchrow($result))
			{
				$user_groups[$row['user_id']][] = array(
					'group_name'	=> (string) $row['group_name'],
					'group_colour'	=> $row['group_colour'],
					'group_id'		=> $row['group_id'],
					'group_type'	=> $row['group_type'],
				);
			}
			$this->db->sql_freeresult($result);

			// cache this data for 5 minutes
			$this->cache->put('_user_groups', $user_groups, 300);
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
		$users_groups = $this->cache->get('_user_groups');
		$user_id = $event['user_poster_data']['user_id'];

		if (!empty($users_groups[$user_id]))
		{
			$user_in_groups = '<ul>';

			foreach ($users_groups[$user_id] as $key => $value)
			{
				if ($value['group_type'] == GROUP_HIDDEN && (!$this->auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel') || $user_id != (int) $this->user->data['user_id']))
				{
					continue;
				}
				$group_name = isset($this->user->lang['G_' . $value['group_name']]) ? $this->user->lang['G_' . $value['group_name']] : $value['group_name'];
				$group_link = append_sid("{$this->root_path}memberlist.$this->php_ext", 'mode=group&amp;g=' . $value['group_id']);
				$group_colour = (!empty($value['group_colour'])) ? 'style="color:#' . $value['group_colour'] . ';"': '';
				$user_in_groups .= '<li><a href=' . $group_link . ' ' . $group_colour . '>' . $group_name . '</a></li>';
			}
			$user_in_groups .= '</ul>';

			$event['post_row'] = array_merge($event['post_row'],array(
			'POSTER_GROUP'		=> $user_in_groups,
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
		$array['user_id'] = $event['row']['user_id'];
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
		$array['user_id'] = $event['row']['user_id'];
		$event['user_cache_data'] = $array;
	}
}
