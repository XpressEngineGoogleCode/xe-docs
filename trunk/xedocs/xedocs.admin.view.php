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
		if(!in_array($this->module_info->module, array('admin', 'xedocs'))) {
			return $this->alertMessage('msg_invalid_request');
		}
		$module_info = Context::get('module_info');

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

	function dispXedocsAdminEditKeyword()
	{
		debug_syslog(1, "dispXedocsAdminEditKeyword\n");
		$has_target_page = false;

		$target_document_srl = Context::get('target_document_srl');

		if( isset($target_document_srl) ){
				debug_syslog(1, "target_document_srl=".$target_document_srl."\n");
				$oDocumentModel = &getModel("document");
				$oDocument = $oDocumentModel->getDocument($target_document_srl);
				$target_title = "No target document";
				if(isset($oDocument)){
					$target_title = $oDocument->getTitle();
					$has_target_page = true;
				}

				Context::set("target_title", $target_title);


		}
		Context::set('has_target_page', $has_target_page);
		debug_syslog(1, "has_target_page=".$has_target_page."\n");

		$this->setTemplateFile("edit_keyword");
		debug_syslog(1, "dispXedocsAdminEditKeyword complete\n");
	}

	function dispXedocsAdminDeleteKeyword()
	{
		debug_syslog(1, "dispXedocsAdminDeleteKeyword\n");
		$module_srl = Context::get('module_srl');
		$keyword = Context::get('keyword');
		if(!isset($keyword)){
			debug_syslog(1, "No keyword to delete \n");
			return;
		}


		debug_syslog(1, "delete keyword=".$keyword." from module_srl=".$module_srl."\n");

		$oXedocsModel = &getModel('xedocs');
		$deleted = $oXedocsModel->delete_keyword($module_srl, $keyword);
		if($deleted){
			debug_syslog(1, "keyword delted\n");
		}
		$this->dispXedocsAdminCompileKeywordList();
		debug_syslog(1, "dispXedocsAdminDeleteKeyword complete\n");

	}


	function dispXedocsAdminAddKeyword()
	{
		debug_syslog(1, "dispXedocsAdminAddKeyword\n");
		$has_target_page = false;
		$module_srl = Context::get('module_srl');
		$target_document_srl = Context::get('target_document_srl');

		if( !isset($target_document_srl) ){
				$oXedocsModel = &getModel('xedocs');
				$target_document_srl = $oXedocsModel->get_first_node_srl($module_srl);
				Context::set('target_document_srl', $target_document_srl);
		}

		debug_syslog(1, "target_document_srl=".$target_document_srl."\n");

		$oDocumentModel = &getModel("document");
		$oDocument = $oDocumentModel->getDocument($target_document_srl);
		$target_title = "No target document";
		if(isset($oDocument)){
			$target_title = $oDocument->getTitle();
			$has_target_page = true;
		}
		Context::set('keyword', "-");
		Context::set("target_title", $target_title);


		$oDocumentModel = &getModel("document");
		$oDocument = $oDocumentModel->getDocument($target_document_srl);
		$page_content = $oDocument->getContent(false);
		Context::set('page_content', $page_content);
		$has_target_page = true;

		Context::set('has_target_page', $has_target_page);
		debug_syslog(1, "has_target_page=".$has_target_page."\n");

		$this->setTemplateFile("add_keyword");
		debug_syslog(1, "dispXedocsAdminAddKeyword complete\n");
	}


	function dispXedocsAdminReviewKeywords(){
		debug_syslog(1, "dispXedocsAdminReviewKeywords\n");
		$document_srl = Context::get('document_srl');
		$module_srl = Context::get('module_srl');

		$oXedocsModel = &getModel('xedocs');

		if( !isset($document_srl) ){
			$document_srl = $oXedocsModel->get_first_node_srl($module_srl);
		}

		debug_syslog(1, "dispXedocsAdminReviewKeywords".print_r($this->grant, true)."\n");

		if(!$this->grant->is_admin){
			return $this->dispXedocsMessage('msg_not_permitted');
		}

		$oModuleModel = &getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);

		debug_syslog(1, "dispXedocsAdminReviewKeywords admin chk ok\n");

		$oDocumentModel = &getModel('document');

		$oDocument = $oDocumentModel->getDocument(0, $this->grant->manager);
		$oDocument->setDocument($document_srl);

		$oDocument->add('module_srl', $module_srl);
		Context::set('document_srl',$document_srl);

		$entry = $oDocumentModel->getAlias($document_srl);
		Context::set('entry', $entry);

		debug_syslog(1, "dispXedocsAdminReviewKeywords entry = ".$entry."\n");

		if(isset($module_info->keywords)){

				$keywords = $oXedocsModel->string_to_keyword_list($module_info->keywords);
				debug_syslog(1, "There are ".count($keywords)." keyword targets\n");
				$kcontent = $oXedocsModel->get_document_content_with_keywords($oDocument, $keywords);
				debug_syslog(1, "got kcontent\n");
				if( 0 < $kcontent->fcount ){
					$content = $kcontent->content;
					debug_syslog(1, "There are ".count($kcontent->links)." links inserted\n");
					Context::set("klinks", $kcontent->links);
				}
				else{
					Context::set("klinks", null);
					debug_syslog(1, "No keywords matched in document\n");
					$content = $oDocument->getContent(false);
				}

			}
		else{
				Context::set("klinks", null);
				$content = $oDocument->getContent(false);
				debug_syslog(1, "No keywords in module\n");
		}

		$oDocument->add('content', $content);
		Context::set("page_content", $content);

		Context::set('oDocument', $oDocument);

		//Context::addJsFilter($this->module_path.'tpl/filter', 'insert.xml');


		list($prev_document_srl, $next_document_srl) = $oXedocsModel->getPrevNextDocument($module_srl, $document_srl);

		if($prev_document_srl){

			$oPrevDocEntry = $oDocumentModel->getAlias($prev_document_srl);
			Context::set('oPrevDocEntry', $oPrevDocEntry);
			Context::set('oDocumentPrev', $oDocumentModel->getDocument($prev_document_srl));
		}

		if($next_document_srl)
		{
			$oNextDocEntry = $oDocumentModel->getAlias($next_document_srl);

			Context::set('oNextDocEntry', $oNextDocEntry);
			Context::set('oDocumentNext', $oDocumentModel->getDocument($next_document_srl));

		}



		$this->setTemplateFile("review_keyword_links");
		debug_syslog(1, "dispXedocsAdminReviewKeywords complete\n");
	}

	function resolve_document_details($oModuleModel, $oDocumentModel, $doc){

		$entry = $oDocumentModel->getAlias($doc->document_srl);

		$module_info = $oModuleModel->getModuleInfoByDocumentSrl($doc->document_srl);
		$doc->browser_title = $module_info->browser_title;
		$doc->mid = $module_info->mid;


		if ( isset($entry) ){
			$doc->entry = $entry;
		}else{
			$doc->entry = "bugbug";
		}

		debug_syslog(1, "resolve_document_details doc_sel=".$doc->document_srl." mid= ".$doc->mid." entry=".$doc->entry."\n");
	}


	function _search_keyword($target_mid, $is_keyword){
		$page =  Context::get('page');
		if (!isset($page)) $page = 1;

		$search_target = Context::get('search_target');
		if ($search_target == 'tag') $search_target = 'tags';

		$oXedocsModel = &getModel('xedocs');
		$oModuleModel = &getModel('module');
		$oDocumentModel = &getModel('document');

		$output = $oXedocsModel->search($is_keyword, $target_mid, $search_target, $page, 10);

		debug_syslog(1, "resolve_document_details for count=".count($output->data)."\n");
		foreach($output->data as $doc){

			$this->resolve_document_details($oModuleModel, $oDocumentModel, $doc);
		}

		Context::set('document_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);

		Context::set('page', $page);
		Context::set('page_navigation', $output->page_navigation);

		return $output;
	}

	function dispXedocsAdminManualPageSelect()
	{
		debug_syslog(1, "dispXedocsAdminManualPageSelect\n");

        if(!Context::get('is_logged')) return new Object(-1, 'msg_not_permitted');

        $oModuleModel = &getModel('module');
		$module_srl = Context::get('module_srl');
		$search_keyword = Context::get('search_keyword');

		debug_syslog(1, "dispXedocsAdminManualPageSelect keyword=".$search_keyword."\n");

		if(isset($search_keyword)){

			$page =  Context::get('page');
			if (!isset($page)) $page = 1;

			$search_target = Context::get('search_target');
			if( isset($search_target) ){
				if ( $search_target == 'tag') $search_target = 'tags';
			}

			debug_syslog(1, "searching ...\n");
			$this->_search_keyword($module_srl, $search_keyword);
		}

		$this->setTemplateFile('document_selector');


		debug_syslog(1, "dispXedocsAdminManualPageSelect complete\n");
	}


	function dispXedocsAdminSelectDocumentList()
	{
			debug_syslog(1, "dispXedocsAdminSelectDocumentList\n");

            if(!Context::get('is_logged')) return new Object(-1, 'msg_not_permitted');

            $oModuleModel = &getModel('module');
			$module_srl = Context::get('module_srl');
			$search_keyword = Context::get('search_keyword');


			$page =  Context::get('page');
			if (!isset($page)) $page = 1;

			$search_target = Context::get('search_target');
			if( isset($search_target) ){
				if ( $search_target == 'tag') $search_target = 'tags';
			}

			$this->_search_keyword($search_keyword);

			$this->setTemplateFile('document_selector');

			debug_syslog(1, "dispXedocsAdminSelectDocumentList complete\n");

	}


	function dispXedocsAdminClearKeywordList()
	{
		debug_syslog(1, "dispXedocsAdminClearKeywordList\n");
		$oXedocsModel = &getModel('xedocs');
		$module_srl = Context::get('module_srl');
		$module_info = Context::get('module_info');


		$oXedocsModel->clear_keywords($module_srl);
		debug_syslog(1, "clear_keywords complete\n");

		$oModuleModel = &getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		Context::set('module_info',$module_info);

		Context::set('total_keywords', 0);
		$this->setTemplateFile("compile_keyword_list");

		debug_syslog(1, "dispXedocsAdminClearKeywordList complete\n");
	}


	function dispXedocsAdminCompileKeywordList()
	{
		debug_syslog(1, "dispXedocsAdminCompileKeywordList\n");
		$module_info = Context::get('module_info');
		$oModuleModel = &getModel('module');
		$oDocumentModel = &getModel('document');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_info->module_srl);
		$oModuleModel->addModuleExtraVars($module_info);

		$manual_set = &getModel('xedocs')->getModuleMidSet($module_info->help_name);
		$module_count = count($manual_set);

		debug_syslog(1, "module_count =".$module_count."\n");
		debug_syslog(1, "help_name =".$module_info->help_name."\n");

		Context::set('module_count',$module_count);
		Context::set('module_info',$module_info);
		Context::set('manual_set', $manual_set);
		$page = Context::get('page');

		$oXedocsModel = &getModel('xedocs');
		if(isset($module_info->keywords)){

				$filter_keyword = Context::get('filter_keyword');
				$items_per_page = 10;
				if(!isset($page)){
					$page = 1;
				}


				$keywords = $oXedocsModel->string_to_keyword_list($module_info->keywords, $filter_keyword);

				$paged_keywords = array();

				$start = $items_per_page * ($page-1);
				$k_count = 0;
				for(; $k_count<$items_per_page && $start < count($keywords); $start++,  $k_count++){
					$obj = $keywords[$start];

					$oDocument = $oDocumentModel->getDocument($obj->target_document_srl);
					$obj->target_title = "No target document";
					if(isset($oDocument))
					{
						$obj->target_title = $oDocument->getTitle();
					}

					$paged_keywords[] =  $obj;
				}

				debug_syslog(1, "There are ".count($keywords)." keyword targets\n");

				$total_keywords = count($keywords);
				Context::set('total_keywords', $total_keywords);
				Context::set('keyword_list', $paged_keywords);


				$total_page = ceil( (float)$total_keywords/$items_per_page );
				$page_navigation = new PageHandler($total_keywords, $total_page, $page, $items_per_page);

				Context::set('total_page', $total_page);
				Context::set('page', $page);
				Context::set('page_navigation', $page_navigation);

			}
		else{
				Context::set('total_keywords', 0);
				debug_syslog(1, "No keywords in module\n");
		}


		$this->setTemplateFile("compile_keyword_list");
		debug_syslog(1, "dispXedocsAdminCompileKeywordList complete\n");
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