<?php

class Sedo_TabsController_Model_GetUsergroups extends XenForo_Model
{
	public function getUserGroupsOptions($selectedUserGroupIds)
	{
		$userGroups = array();
		foreach ($this->getDbUserGroups() AS $userGroup)
		{
			$userGroups[] = array(
				'label' => filter_var($userGroup['title'], FILTER_SANITIZE_STRING),
				'value' => $userGroup['user_group_id'],
				'selected' => in_array($userGroup['user_group_id'], $selectedUserGroupIds)
			);
		}

		return $userGroups;
	}

	public function getDbUserGroups()
	{
		return $this->_getDb()->fetchAll('
			SELECT user_group_id, title
			FROM xf_user_group
			WHERE user_group_id
			ORDER BY user_group_id
		');
	}
}