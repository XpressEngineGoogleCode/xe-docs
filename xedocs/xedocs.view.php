<?php
/**
 * @class  xedocsView
 **/

class xedocsView extends xedocs {



	var $search_option = array('title','content','title_content','comment','user_name','nick_name','user_id','tag');

	function init() {
		$template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);

		if(!is_dir($template_path) || !$this->module_info->skin) {
			$this->module_info->skin = 'xe_xedocs';
			$template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
		}

		$this->setTemplatePath($template_path);

		$oModuleModel = &getModel('module');

		$document_config = $oModuleModel->getModulePartConfig('document', $this->module_info->module_srl);

		if(!isset($document_config->use_history)){

			$document_config->use_history = 'N';
		}

		$this->use_history = $document_config->use_history;
		Context::set('use_history', $document_config->use_history);

		Context::addJsFile($this->module_path.'tpl/js/manual.js');

		Context::set('grant', $this->grant);


	}

	function dispXedocsContent() {
		debug_syslog(1, "dispXedocsContent\n");
		$output = $this->dispXedocsContentView();
		if(!$output->toBool()){
			return;
		}


		
	}

	function dispXedocsHistory()
	{
		$oDocumentModel = &getModel('document');
		$document_srl = Context::get('document_srl');
		$page = Context::get('page');
		$oDocument = $oDocumentModel->getDocument($document_srl);

		if(!$oDocument->isExists()){
			return $this->stop('msg_invalid_request');
		}

		$entry = $oDocument->getTitleText();
		Context::set('entry',$entry);
		$output = $oDocumentModel->getHistories($document_srl, 10, $page);
		if(!$output->toBool() || !$output->data) {

			Context::set('histories', array());
		}
		else {
			Context::set('histories',$output->data);
			Context::set('page', $output->page);
			Context::set('page_navigation', $output->page_navigation);
		}

		Context::set('oDocument', $oDocument);
		$this->setTemplateFile('histories');
	}


	function resolve_firstdocument(){
		$oModuleModel = &getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($this->module_srl);
			
		return  $module_info->first_node_srl;
	}

	function dispXedocsEditPage()
	{

		debug_syslog(1, "dispXedocsEditPage".print_r($this->grant, true)."\n");

		if(!$this->grant->is_admin){
			return $this->dispXedocsMessage('msg_not_permitted');
		}


		debug_syslog(1, "dispXedocsEditPage admin chk ok\n");

		$oDocumentModel = &getModel('document');
		$document_srl = Context::get('document_srl');

		if (!isset($document_srl) ) //resolve first document
		{
			$document_srl = $this->resolve_firstdocument();
		}



		$oDocument = $oDocumentModel->getDocument(0, $this->grant->manager);
		$oDocument->setDocument($document_srl);
		$oDocument->add('module_srl', $this->module_srl);
		Context::set('document_srl',$document_srl);

		Context::set('oDocument', $oDocument);
		$history_srl = Context::get('history_srl');
		if($history_srl) {

			$output = $oDocumentModel->getHistory($history_srl);
			if($output && $output->content != null){

				Context::set('history', $output);
			}
		}

		Context::addJsFilter($this->module_path.'tpl/filter', 'insert.xml');

		$this->setTemplateFile('write_form');
	}

	/**
	 * @brief Displaying Message
	 **/
	function dispXedocsMessage($msg_code)
	{
		$msg = Context::getLang($msg_code);
		if(!$msg){
			$msg = $msg_code;
		}
		Context::set('message', $msg);
		$this->setTemplateFile('message');
	}

	function sortArrayByKeyDesc($object_array, $key ){
		
		debug_syslog(1 , "sortArrayByKeyDesc key=".$key." obj_count=".count($object_array)."\n");
		
		$key_array = array();
		foreach($object_array as $obj ){
			debug_syslog(1, " key value = ".$obj->{$key}."\n" );
			$key_array[$obj->{$key}] = $obj;
		}
		
		krsort($key_array);
		
		$result = array();
		foreach($key_array as $rank => $obj ){
			$result[] = $obj;
		}
		return $result;
	}

	function dispXedocsSearchResults(){
		debug_syslog(1, "dispXedocsSearchResults search \n");
		
		$oLuceneModel = &getModule('lucene');
		debug_syslog(1, "dispXedocsSearchResults search 1\n");
		if( !isset($oLuceneModel) ){
			debug_syslog(1, "dispXedocsSearchResults fallback \n");
			return $this->dispXedocsSearchResults_fallback();
		}
		
		$oLuceneModel = &getModel('xedocs');
		$oDocumentModel = &getModel('document');
		$oModuleModel = &getModel('module');
		$oXedocsModel = &getModel('xedocs');

		
		$moduleList = $oXedocsModel->getModuleList(true);
		
		$moduleList = $this->sortArrayByKeyDesc($moduleList, 'search_rank');
		
		Context::set('module_list', $moduleList);

		debug_syslog(1, "dispXedocsSearchResults lucene search \n");
		$searchAPI = "lucene_search_bloc-1.0/SearchBO/";

		$searchUrl = $oLuceneModel->getDefaultSearchUrl($searchAPI);
		debug_syslog(1, "searchUrl=".$searchUrl."\n");

		$target_mid = $this->module_info->module_srl;
		$is_keyword = Context::get("search_keyword");
		
		$page = (int) Context::get('page');
		if (!$page) $page = 1;
		$search_target = Context::get('search_target');
		if ($search_target == 'tag') $search_target = 'tags';

		debug_syslog(1, "setup complete.target_mid=".$target_mid." now checking\n");

		if(!$oLuceneModel->isFieldCorrect($search_target)){
		  $search_target = 'title_content';
		}
		debug_syslog(1, "search_target=".$search_target."\n");

		//Search queries applied to the target module
		$query = $oLuceneModel->getSubquery($target_mid, "include", null); 

		debug_syslog(1, "subquery=".$query."\n");
		//Parameter setting
		$json_obj->query = $oLuceneModel->getQuery($is_keyword, $search_target, null);
		$json_obj->curPage = $page;
		$json_obj->numPerPage = 10;
		$json_obj->indexType = "db";
		$json_obj->fieldName = $search_target;
		$json_obj->target_mid = $target_mid;
		$json_obj->target_mode = $target_mode;
		
		$json_obj->subquery = $query;

		$output = $oLuceneModel->getDocuments($searchUrl, $json_obj);

		foreach($output->data as $doc){

			$this->resolve_document_details($oModuleModel, $oDocumentModel, $doc);
		}
		

		//debug_syslog(1, "document_search: ".print_r($output, true)."\n");

		Context::set('document_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('total_page', 1);
		Context::set('page', $page);
		Context::set('page_navigation', $output->page_navigation);


		$this->setTemplateFile('search_results');
		
		debug_syslog(1, "dispXedocsSearchResults complete\n");

	}

	function dispXedocsSearchResults_fallback()
	{

		$page = Context::get('page');
		$oDocumentModel = &getModel('document');
		$oXedocsModel = &getModel('xedocs');
		
		$moduleList = $oXedocsModel->getModuleList(true);
		$moduleList = $this->sortArrayByKeyDesc($moduleList, 'search_rank');
		Context::set('module_list', $moduleList);
		
		$obj->module_srl =array($this->module_srl); 
			
		$obj->page = $page;
		$obj->list_count = 10;

		$obj->exclude_module_srl = '0';
		$obj->sort_index = 'module';
		//$obj->order_type = 'asc';
		$obj->search_keyword = Context::get('search_keyword');
		$obj->search_target = Context::get('search_target');
		$output = $oDocumentModel->getDocumentList($obj);

		$oModuleModel = &getModel('module');

		foreach($output->data as $doc){

			$this->resolve_document_details($oModuleModel, $oDocumentModel, $doc);
		}
		
		
		debug_syslog(1, "Count(document_list)=".count($output->data)."\n");
		Context::set('document_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);


		foreach($this->search_option as $opt){
			$search_option[$opt] = Context::getLang($opt);
		}
		Context::set('search_option', $search_option);

		$this->setTemplateFile('search_results');

		debug_syslog(1, "dispXedocsSearchResults_fallback complete\n");
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

	function dispXedocsTitleIndex()
	{
		$page = Context::get('page');
		$oDocumentModel = &getModel('document');
		$obj->module_srl = $this->module_info->module_srl;
		$obj->sort_index = 'update_order';
		$obj->page = $page;
		$obj->list_count = 50;

		$obj->search_keyword = Context::get('search_keyword');
		$obj->search_target = Context::get('search_target');
		$output = $oDocumentModel->getDocumentList($obj);

		Context::set('document_list', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);


		foreach($this->search_option as $opt){
			$search_option[$opt] = Context::getLang($opt);
		}
		Context::set('search_option', $search_option);

		$this->setTemplateFile('title_index');
	}

	function dispXedocsTreeIndex()
	{
		debug_syslog(1, "dispXedocsTreeIndex\n" );
		$oXedocsModel = &getModel('xedocs');
		
		$value = $oXedocsModel->readXedocsTreeCache($this->module_srl);
		Context::set('list', $value);
		

		$document_srl = Context::get('document_srl');
		debug_syslog(1, "Context document_srl=".$document_srl."\n" );
		$oModuleModel = &getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($this->module_srl);
		debug_syslog(1, "module_info=".print_r($module_info, true)."\n" );
		Context::set("module_info", $module_info);
		$has_page = true;
		if (!isset($document_srl) )
		{
			if(!isset($module_info->first_node_srl))
			{
				foreach( $value as $i=>$obj){
					$document_srl = $obj->document_srl;
					debug_syslog(1, "dispXedocsTreeIndex setting document_srl to first_node_srl 0\n" );
					break;
				}
			}else{
				$document_srl = $module_info->first_node_srl;
				debug_syslog(1, "dispXedocsTreeIndex setting document_srl to first_node_srl\n" );
			}
			
		}else{
			//check document_srl
			if(!$oXedocsModel->check_document_srl($document_srl, $module_info))
			{
				$has_page = false;
				debug_syslog(1, "bad document_srl: ".$document_srl."\n" );
				
				//we stil need first doc 
				if(!isset($module_info->first_node_srl))
				{
					foreach( $value as $i=>$obj){
						$document_srl = $obj->document_srl;
						debug_syslog(1, "dispXedocsTreeIndex setting document_srl to first_node_srl 1\n" );
						break;
					}
				}else{
					$document_srl = $module_info->first_node_srl;
					debug_syslog(1, "dispXedocsTreeIndex setting document_srl to first_node_srl 2\n" );
				}
				
				
			}else{
				
				debug_syslog(1, "document_srl: ".$document_srl." ok\n" );
			}
			
		}
		
		debug_syslog(1, "has_page: ".$has_page."\n" );
		Context::set('has_page', $has_page);

		$this->setTemplateFile('tree_list');
		
		debug_syslog(1, "dispXedocsTreeIndex: document_srl=".$document_srl."\n");

		$oDocumentModel = &getModel("document");
		$oDocument = $oDocumentModel->getDocument($document_srl);

		Context::set('oDocument', $oDocument);

		
		
		if($has_page){
			$content = $oDocument->getContent(false);
			
			if(isset($module_info->keywords)){
				
				$keywords = $oXedocsModel->string_to_keyword_list($module_info->keywords);
				debug_syslog(1, "There are ".count($keywords)." keyword targets\n");
				$kcontent = $oXedocsModel->get_document_content_with_keywords($content, $keywords);
				if( 0 < $kcontent->fcount ){
					$content = $kcontent->content; 
				}
			}
			else{
				debug_syslog(1, "No keywords in module\n");
			}
			$oDocument->add('content', $content);
			Context::set("page_content", $content);
			
		}
		
		$module_srl=$oDocument->get('module_srl');
		$parents = $oXedocsModel->getParents($document_srl, $module_srl);
		Context::set("parents", $parents);

		$children = $oXedocsModel->getChildren($document_srl, $module_srl);
		Context::set("children", $children);

		$siblings = $oXedocsModel->getSiblings($document_srl, $module_srl);
		Context::set("siblings", $siblings);


			
		$versions = $oXedocsModel->get_versions($module_srl, $oDocument);

		$version_labels = $this->format_versions(trim($versions), $document_srl);
		Context::set("version_labels",  $version_labels );

		$meta = $oXedocsModel->get_meta($module_srl, $document_srl);
		Context::set("meta", $meta);

		Context::setBrowserTitle($module_info->browser_title." - ".$oDocument->getTitle());

		list($prev_document_srl, $next_document_srl) = $oXedocsModel->getPrevNextDocument($this->module_srl, $document_srl);

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
		debug_syslog(1, "dispXedocsTreeIndex complete\n" );

	}


	function dispXedocsCommentEditor()
	{
		debug_syslog(1, "dispXedocsCommentEditor\n");
		$document_srl = Context::get('document_srl');
		$oModuleModel = &getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($this->module_srl);
		Context::set("module_info", $module_info);

		if (!isset($document_srl) )
		{
			if(!isset($module_info->first_node_srl))
			{
				foreach( $value as $i=>$obj){
					$document_srl = $obj->document_srl;
					break;
				}
			}else{
				$document_srl = $module_info->first_node_srl;
			}
		}

		Context::set('has_page', true);
		debug_syslog(1, "dispXedocsCommentEditor: document_srl=".$document_srl."\n");

		$oDocumentModel = &getModel("document");
		$oDocument = $oDocumentModel->getDocument($document_srl);

		Context::set('oDocument', $oDocument);

		$this->setTemplateFile('comment_editor');
		Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');
	}

	function get_document_link($document_srl)
	{

		if( !isset($document_srl) ) return false;

		$oDocumentModel = &getModel('document');
		$oModuleModel = &getModel('module');

		$module_info = $oModuleModel->getModuleInfoByDocumentSrl($document_srl);

		$site_module_info = Context::get('site_module_info');

		$entry = $oDocumentModel->getAlias($document_srl);

		if($entry){

			$url = getSiteUrl($site_module_info->document,'','mid',$module_info->mid,'entry',$entry);

		}else{


			$url = getSiteUrl($site_module_info->document,'','mid', $module_info->mid, 'document_srl',$document_srl);
		}

		return $url;

	}


	function format_versions($versions, $document_srl)
	{
		if( !isset($versions) || 0 == strcmp('', $versions)){
			return "";
		}

		$result = array();

		$varr = explode("|", $versions);
		$labels = array();
		$sversions = array();
		foreach($varr as $v){
			$values = explode("->", $v);

			$label = $values[0];
			$doc_srl = $values[1];
			$labels[] = $label;
			$sversions[$label] = $doc_srl;
			//$result .= "<a href='".$this->get_document_link($doc_srl)."'> ".$label." </a> &nbsp";
		}

		sort($labels);
		//debug_syslog(1,"Sorted versions: ".print_r($labels, true)."\n");

		foreach($labels as  $l)
		{
			$doc_srl = $sversions[$l];

			$obj = null;

			$obj->{'is_current_version'}= $doc_srl == $document_srl;

			$obj->{'doc_srl'} = $doc_srl;
			$obj->{'vlabel'} = $l;

			if( $doc_srl != $document_srl ){
				$obj->{'href'} = $this->get_document_link($doc_srl);
			}else{
				$obj->{'href'} = "";
			}

			$result[] = $obj;
		}
		//debug_syslog(1,"Result versions: ".print_r($result, true)."\n");
		return $result;

	}

	function dispXedocsModifyTree()
	{
		if(!$this->grant->is_admin) {

			return new Object(-1,'msg_not_permitted');
		}

		Context::set('isManageGranted', $this->grant->is_admin?'true':'false');
		$this->setTemplateFile('modify_tree');
	}

	function addToVisitLog($entry)
	{
		$module_srl = $this->module_info->module_srl;
		$visit_log = $_SESSION['xedocs_visit_log'];
		if(! $visit_log) {

			$visit_log =   $_SESSION['xedocs_visit_log'] = array();
		}
		if(!$visit_log[$module_srl]
		|| !is_array($visit_log[$module_srl])) {

			$visit_log[$module_srl] = array();
		}
		else {

			foreach($visit_log[$module_srl] as $key => $value){

				if($value == $entry){

					unset($visit_log[$module_srl][$key]);
				}
			}

			if( 5 <= count($visit_log[$module_srl]) ) {

				array_shift($visit_log[$module_srl]);
			}
		}
		$visit_log[$module_srl][] = $entry;
	}

	function callback_xedocslink($matches)
	{
		$names = explode("|", $matches[1]);
		if(count($names) == 2)
		{
			return "<a href=\"".getUrl('entry',$names[0])."\" class=\"inlink\" >".$names[1]."</a>";
		}
		return "<a href=\"".getUrl('entry',$matches[1])."\" class=\"inlink\" >".$matches[1]."</a>";
	}

	function dispXedocsContentView()
	{
		$oXedocsModel = &getModel('xedocs');
		$oDocumentModel = &getModel('document');

		$document_srl = Context::get('document_srl');
		$entry = Context::get('entry');
		if(!$document_srl && !$entry) {
			$entry = "Front Page";
			Context::set('entry', $entry);
			$document_srl = $oDocumentModel->getDocumentSrlByAlias($this->module_info->mid, $entry);
		}

		if($document_srl) {
			$oDocument = $oDocumentModel->getDocument($document_srl);


			if($oDocument->isExists()) {

				if($oDocument->get('module_srl')!=$this->module_info->module_srl ){
					return $this->stop('msg_invalid_request');
				}

				if($this->grant->manager) $oDocument->setGrant();

				if(!$entry) {

					$entry = $oDocument->getTitleText();
					Context::set('entry', $entry);
				}


				$history_srl = Context::get('history_srl');
				if($history_srl) {

					$output = $oDocumentModel->getHistory($history_srl);
					if($output && $output->content != null){

						Context::set('history', $output);
					}
				}

				$content = $oDocument->getContent(false);
				//$content = preg_replace_callback("!\[([^\]]+)\]!is", array( $this, 'callback_xedocslink' ), $content );
				$oDocument->add('content', $content);


				list($prev_document_srl, $next_document_srl) = $oXedocsModel->getPrevNextDocument($this->module_srl, $document_srl);
				if($prev_document_srl){
					Context::set('oDocumentPrev', $oDocumentModel->getDocument($prev_document_srl));
				}
				if($next_document_srl){
					Context::set('oDocumentNext', $oDocumentModel->getDocument($next_document_srl));
				}
				$this->addToVisitLog($entry);


			} else {
				Context::set('document_srl','',true);
				$this->alertMessage('msg_not_founded');
			}

		} else {
			$oDocument = $oDocumentModel->getDocument(0);
		}

		if($oDocument->isExists()) {

			Context::addBrowserTitle($oDocument->getTitleText());


			if(!$oDocument->isSecret() || $oDocument->isGranted()){
				$oDocument->updateReadedCount();
			}

			if($oDocument->isSecret() && !$oDocument->isGranted()){
				$oDocument->add('content',Context::getLang('thisissecret'));
			}

			$module_srl=$oDocument->get('module_srl');
			$parents = $oXedocsModel->getParents($document_srl, $module_srl);
			$p_count = count($parents);
			Context::set('p_count1',$p_count);

			Context::set('parents',$parents);

			$this->setTemplateFile('view_document');

			// set contributors
			if($this->use_history) {

				$contributors = $oXedocsModel->getContributors($oDocument->document_srl);
				Context::set('contributors', $contributors);
			}


			if($this->module_info->use_comment != 'N'){
				$oDocument->add('allow_comment','Y');
			}
		}
		else {
			$module_srl=$oDocument->get('module_srl');
			$parents = $oXedocsModel->getParents($document_srl, $module_srl);
			$p_count = count($parents);
			Context::set('p_count2',$p_count);
			Context::set('parents',$parents);

			$this->setTemplateFile('create_document');
		}

		Context::set('visit_log', $_SESSION['xedocs_visit_log'][$this->module_info->module_srl]);

		Context::set('oDocument', $oDocument);

		Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');

		return new Object();
	}

	function dispXedocsReplyComment()
	{

		if(!$this->grant->write_comment){
			return $this->dispXedocsMessage('msg_not_permitted');
		}


		$parent_srl = Context::get('comment_srl');


		if(!$parent_srl) {
			return new Object(-1, 'msg_invalid_request');
		}


		$oCommentModel = &getModel('comment');
		$oSourceComment = $oCommentModel->getComment($parent_srl, $this->grant->manager);


		if(!$oSourceComment->isExists()) {
			return $this->dispXedocsMessage('msg_invalid_request');
		}

		if( Context::get('document_srl')
		&& $oSourceComment->get('document_srl') != Context::get('document_srl')) {

			return $this->dispXedocsMessage('msg_invalid_request');
		}


		$oComment = $oCommentModel->getComment();
		$oComment->add('parent_srl', $parent_srl);
		$oComment->add('document_srl', $oSourceComment->get('document_srl'));


		Context::set('oSourceComment',$oSourceComment);
		Context::set('oComment',$oComment);

		Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');

		$this->setTemplateFile('comment_form');
	}

	function dispXedocsModifyComment()
	{

		if(!$this->grant->write_comment){
			return $this->dispXedocsMessage('msg_not_permitted');
		}


		$document_srl = Context::get('document_srl');
		$comment_srl = Context::get('comment_srl');


		if(!$comment_srl) {
			return new Object(-1, 'msg_invalid_request');
		}

		$oCommentModel = &getModel('comment');
		$oComment = $oCommentModel->getComment($comment_srl, $this->grant->manager);

		if(!$oComment->isExists()) {
			return $this->dispXedocsMessage('msg_invalid_request');
		}


		if(!$oComment->isGranted()) {
			return $this->setTemplateFile('input_password_form');
		}


		Context::set('oSourceComment', $oCommentModel->getComment());
		Context::set('oComment', $oComment);

		Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');

		$this->setTemplateFile('comment_form');
	}

	function dispXedocsDeleteComment()
	{

		if(!$this->grant->write_comment){
			return $this->dispXedocsMessage('msg_not_permitted');
		}

		$comment_srl = Context::get('comment_srl');


		if($comment_srl) {
			$oCommentModel = &getModel('comment');
			$oComment = $oCommentModel->getComment($comment_srl, $this->grant->manager);
		}


		if(!$oComment->isExists() ) {
			return $this->dispXedocsContent();
		}

		if(!$oComment->isGranted()){
			return $this->setTemplateFile('input_password_form');
		}

		Context::set('oComment',$oComment);

		Context::addJsFilter($this->module_path.'tpl/filter', 'delete_comment.xml');

		$this->setTemplateFile('delete_comment_form');
	}

}
?>
