<?php



class xedocsAdminView extends xedocs {

	function init()
	{
		$module_srl = Context::get('module_srl');
		if(!$module_srl && $this->module_srl) {
			$module_srl = $this->module_srl;
			Context::set('module_srl', $module_srl);
		}

		$oModuleModel = &getModel('module');

		if($module_srl) {
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			if(!$module_info) {
				Context::set('module_srl','');
				$this->act = 'list';
			} else {
				ModuleModel::syncModuleToSite($module_info);
				$this->module_info = $module_info;
				Context::set('module_info',$module_info);
			}
		}

		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);

		$this->setTemplatePath($this->module_path.'tpl');
	}

	function dispXedocsAdminView() {

		/****************/
		$args->sort_index = "module_srl";
		$args->page = Context::get('page');
		$args->list_count = 20;
		$args->page_count = 10;
		$args->s_module_category_srl = Context::get('module_category_srl');
		$output = executeQueryArray('xedocs.getManualList', $args);
		ModuleModel::syncModuleToSite($output->data);

		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('manual_list', $output->data);
		Context::set('page_navigation', $output->page_navigation);


		/****************/

		$this->setTemplateFile('index');
	}

	function dispXedocsAdminManualInfo()
	{
	}

	function dispXedocsAdminGrantInfo()
	{
		$oModuleAdminModel = &getAdminModel('module');
		$grant_content = $oModuleAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
		Context::set('grant_content', $grant_content);

		$this->setTemplateFile('grant_list');
	}

	function dispXedocsAdminSkinInfo()
	{
		// Call the common page for managing skin information
		$oModuleAdminModel = &getAdminModel('module');
		$skin_content = $oModuleAdminModel->getModuleSkinHTML($this->module_info->module_srl);
		Context::set('skin_content', $skin_content);

		$this->setTemplateFile('skin_info');
	}


	function dispXedocsAdminInsertManual()
	{
		debug_syslog(1, "dispXedocsAdminInsertManual\n");

		if(!in_array($this->module_info->module, array('admin', 'xedocs'))) {
			return $this->alertMessage('msg_invalid_request');
		}

		$oModuleModel = &getModel('module');
		$skin_list = $oModuleModel->getSkins($this->module_path);
		Context::set('skin_list',$skin_list);

		$oLayoutMode = &getModel('layout');
		$layout_list = $oLayoutMode->getLayoutList();
		Context::set('layout_list', $layout_list);

		$this->setTemplateFile('manual_insert');

	}
	
	

	function dispXedocsAdminImportManual()
	{
		if(!in_array($this->module_info->module, array('admin', 'xedocs'))) {
			return $this->alertMessage('msg_invalid_request');
		}

		$oModuleModel = &getModel('module');
		$skin_list = $oModuleModel->getSkins($this->module_path);
		Context::set('skin_list',$skin_list);

		$oLayoutMode = &getModel('layout');
		$layout_list = $oLayoutMode->getLayoutList();
		Context::set('layout_list', $layout_list);


		$this->setTemplateFile('manual_import');

	}

	function dispXedocsAdminCompileVersion(){

		debug_syslog(1, dispXedocsAdminCompileVersion);
		$module_info = Context::get('module_info');
		$oModuleModel = &getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_info->module_srl);
		$oModuleModel->addModuleExtraVars($module_info);
		
		$manual_set = &getModel('xedocs')->getModuleMidSet($module_info->help_name);
		$module_count = count($manual_set);

		syslog(1, "module_count =".$module_count."\n");
		syslog(1, "help_name =".$module_info->help_name."\n");
		
		Context::set('module_count',$module_count);	
		Context::set('module_info',$module_info);
		Context::set('manual_set', $manual_set);
		
		$this->setTemplateFile("compile_version_labels");
	}
	

	function dispXedocsAdminDeleteManual()
	{
		debug_syslog(1, "dispXedocsAdminDeleteManual\n");
		if(!Context::get('module_srl')){
			return $this->dispXedocsAdminContent();
		}

		if(!in_array($this->module_info->module, array('admin', 'xedocs'))) {
			return $this->alertMessage('msg_invalid_request');
		}

		$module_info = Context::get('module_info');

		$oDocumentModel = &getModel('document');
		$document_count = $oDocumentModel->getDocumentCount($module_info->module_srl);
		$module_info->document_count = $document_count;

		Context::set('module_info',$module_info);

		$this->setTemplateFile('manual_delete');
	}

}


?>