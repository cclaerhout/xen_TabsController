<?php
class Sedo_TabsController_Listener
{
	protected static $_viewName;

	public static function controllerPreView(XenForo_FrontController $fc, 
		XenForo_ControllerResponse_Abstract &$controllerResponse,
		XenForo_ViewRenderer_Abstract &$viewRenderer,
		array &$containerParams
	)
	{
      		self::$_viewName = (isset($controllerResponse->viewName)) ? $controllerResponse->viewName : NULL;
      	}

	public static function render_usergroups(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['formatParams'] = XenForo_Model::create('Sedo_TabsController_Model_GetUsergroups')->getUserGroupsOptions($preparedOption['option_value']);
		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal('option_list_option_checkbox', $view, $fieldPrefix, $preparedOption, $canEdit);
	}

	public static function container_public_params(array &$params, XenForo_Dependencies_Abstract $dependencies)
	{
		$visitor = XenForo_Visitor::getInstance();
		$visitorUserGroupIds = array_merge(array((string)$visitor['user_group_id']), (explode(',', $visitor['secondary_group_ids'])));

		$xenOptions = XenForo_Application::get('options');		
		$disableMembersUsergroups = $xenOptions->sedo_tabs_members_usergroups;
		$disableHelpUsergroups = $xenOptions->sedo_tabs_help_usergroups;
		
		$keysToDisable = array();
		if(	$xenOptions->sedo_tabs_members_disable
			||
			(!empty($disableMembersUsergroups) && array_intersect($visitorUserGroupIds, $disableMembersUsergroups))
		)
		{
			$keysToDisable[] = 'members';
		}
		
		if(	$xenOptions->sedo_tabs_help_disable
			||
			(!empty($disableMembersUsergroups) && array_intersect($visitorUserGroupIds, $disableHelpUsergroups))
		)
		{
			$keysToDisable[] = 'help';		
		}
		
		if(empty($keysToDisable))
		{
			return;
		}

		foreach($keysToDisable as $key)
		{
			if(!isset($params['tabs'], $params['tabs'][$key]))
			{
				continue;
			}
			
			if(!empty($params['tabs'][$key]['selected']))
			{
				continue;
			}
			
			unset($params['tabs'][$key]);
		}
	}
	
	public static function nav_tabs(array &$extraTabs, $selectedTabId)
	{
		if($selectedTabId == '#sedo_disable#')
		{
			return;
		}

		$visitor = XenForo_Visitor::getInstance();
		$visitorUserGroupIds = array_merge(array((string)$visitor['user_group_id']), (explode(',', $visitor['secondary_group_ids'])));
		
		$xenOptions = XenForo_Application::get('options');
		$extraTabsConfig = $xenOptions->sedo_extratabs_ctrl_settings;
		//Zend_Debug::dump($extraTabsConfig);

		if(empty($extraTabsConfig))
		{
			return;
		}

		$extraTabsSort = array_flip(array_keys($extraTabsConfig));
		$extraTabsSort = array_intersect_key($extraTabsSort, $extraTabs);
		$extraTabsSort = array_flip($extraTabsSort);

		$newExtraTabs = array();

		foreach($extraTabsSort as $tabKey)
		{
			$delete = false;

			if(!empty($extraTabsConfig[$tabKey]['disableTab']))
			{
				unset($extraTabs[$tabKey]);
				continue;
			}

			if(!empty($extraTabsConfig[$tabKey]['disableUsergroups']))
			{
				if(array_intersect($visitorUserGroupIds, $extraTabsConfig[$tabKey]['disableUsergroups']))
				{
					unset($extraTabs[$tabKey]);
					continue;
				}
			}
			
			if(!empty($extraTabsConfig[$tabKey]['disableView']))
			{
				$exludedViews = array_map('trim', explode("\n", $extraTabsConfig[$tabKey]['disableView']));

				if(in_array(self::$_viewName, $exludedViews))
				{
					unset($extraTabs[$tabKey]);
					continue;
				}
			}

			$newExtraTabs[$tabKey] = $extraTabs[$tabKey];
			unset($extraTabs[$tabKey]);

			$position = $extraTabsConfig[$tabKey]['position'];
			if($position == "misc")
			{
				$position = '';
			}
			$newExtraTabs[$tabKey]['position'] = $position;	

			if(!empty($extraTabsConfig[$tabKey]['extraClass']))
			{
				$newExtraTabs[$tabKey]['extraClass'] = $extraTabsConfig[$tabKey]['extraClass'];
			}
		}

		foreach($extraTabs as $tabKey => $tabData)
		{
			$newExtraTabs[$tabKey] = $tabData;
		}

		$extraTabs = $newExtraTabs;
		//Zend_Debug::dump($extraTabs);
	}
	
	public static function tabs_settings(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
      		$config = $preparedOption['option_value'];
      		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
      			'preparedOption' => $preparedOption,
      			'canEditOptionDefinition' => $canEdit
      		));
      		      		
		$extraTabs = array();
		XenForo_CodeEvent::fire('navigation_tabs', array(&$extraTabs, '#sedo_disable#'));
		$backupExtraTabs = $extraTabs;

		if(!empty($config))
		{
			$extraTabs = array_replace_recursive($extraTabs, $config);
		}

		$defaultPosition = array('home', 'middle', 'end', 'misc');
		$tabsOptions = array('home' => array(), 'middle' => array(), 'end' => array(), 'misc' => array());
		$tabsOptionsSort = array('home' => array(), 'middle' => array(), 'end' => array(), 'misc' => array());

		foreach($extraTabs as $tabKey => $tabData)
		{
			if(empty($tabData['position']) || !in_array($tabData['position'], $defaultPosition))
			{
				$tabData['position'] = 'misc';
			}

			$originalPosition = (empty($backupExtraTabs[$tabKey]['position'])) ? 'misc' : $backupExtraTabs[$tabKey]['position'];
			$originalOrder = (isset($tabsOptions[$tabData['position']])) ? count($tabsOptions[$tabData['position']])+1 : 1;

			if(!isset($tabData['order']))
			{
				$tabData['order'] = $originalOrder;
			}
			
			if(!isset($tabData['extraClass']))
			{
				$tabData['extraClass'] = '';
			}

			if(!isset($tabData['disableTab']))
			{
				$tabData['disableTab'] = false;
			}
			
			if(!isset($tabData['disableUsergroups']))
			{
				$tabData['disableUsergroups'] = array();
			}

			if(!isset($tabData['disableView']))
			{
				$tabData['disableView'] = '';
			}

			$usergroupsCheckbox = array(
				'formatParams' => XenForo_Model::create('Sedo_TabsController_Model_GetUsergroups')->getUserGroupsOptions($tabData['disableUsergroups']),
				'option_id' => $preparedOption['option_id'] . "][$tabKey][disableUsergroups][", //will be wrapped with [] :  name="{$fieldPrefix}[{$preparedOption.option_id}]"
				'title' => new XenForo_Phrase('sedo_tabs_ctrl_disable_tab_for_usergroups'),
				'explain' => ''
			);
			$checkboxTemplate_usr = XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal('option_list_option_checkbox', $view, $fieldPrefix, $usergroupsCheckbox, false);

			$tabsOptions[$tabData['position']][$tabKey] = array(
				'order' => $tabData['order'],
				'extraClass' => $tabData['extraClass'],
				'disableUsergroups' => $tabData['disableUsergroups'],
				'disableView' => $tabData['disableView'],
				'disableTag' => $tabData['disableTab'],				
				'original' => array(
					'order' => $originalOrder,
					'position' => $originalPosition
				),
				'checkboxTemplate_usr' => $checkboxTemplate_usr
			);

			$tabsOptionsSort[$tabData['position']][$tabKey] = $tabData['order'];
		}
		
		foreach($tabsOptions as $position => &$tabs)
		{
			array_multisort($tabsOptionsSort[$position], SORT_ASC, $tabs);
		}
		
      		return $view->createTemplateObject('option_list_sedo_tabs_settings', array(
      			'fieldPrefix' => $fieldPrefix,
      			'listedFieldName' => $fieldPrefix . '_listed[]',
      			'preparedOption' => $preparedOption,
      			'formatParams' => $preparedOption['formatParams'],
      			'editLink' => $editLink,
			'tabsList' => $tabsOptions,
			'defaultPosition' => array('home', 'middle', 'end', 'misc'),
      			'config' => $config			
      		));
	}

	public static function tabs_settings_validation(array &$configs, XenForo_DataWriter $dw, $fieldName)
	{
		if(!empty($configs['raz']))
		{
			unset($configs['raz']);
			
			foreach($configs as $tabKey => &$tabData)
			{
				 $tabData['order'] = $tabData['original']['order'];
				 $tabData['position'] = $tabData['original']['position'];
				 $tabData['extraClass'] = '';
				 $tabData['disableUsergroups'] = array();
				 $tabData['disableTab'] = false;
				 $tabData['disableView'] = '';
			}
			return true;
		}

		$defaultPosition = array('home', 'middle', 'end', 'misc');
		$tmpArray = array();
		
		foreach($configs as $tabKey => &$tabData)
		{
			$position = $tabData['position'];
			$tabData['order'] = intval($tabData['order']);
			
			if(empty($tabData['disableUsergroups']) || !is_array($tabData['disableUsergroups']))
			{
				$tabData['disableUsergroups'] = array();
			}
			
			if(empty($tabData['disableTab']))
			{
				$tabData['disableTab'] = false;
			}

			if(empty($tabData['disableView']))
			{
				$tabData['disableView'] = '';
			}
		
			if(!in_array($position, $defaultPosition))
			{
				$position = 'misc';			
			}
		
			$tmpArray[$position][$tabKey] = $tabData;
		}

		foreach($tmpArray as &$position)
		{
			$sortRef = array();

			foreach($position as $tabKey => $tabData)
			{
				$sortRef[$tabKey] = $tabData['order'];			
			}
			
			array_multisort($sortRef, SORT_ASC, $position);
			$i = 1;
			
			foreach($position as $tabKey => $tabData)
			{
				$configs[$tabKey]['order'] = $i;
				$i++;			
			}
		}

		return true;
	}
}
//Zend_Debug::dump($configs);