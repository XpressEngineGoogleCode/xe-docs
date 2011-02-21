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

	function dispXedocsEditPage()
	{
		if(!$this->grant->write_document){
			return $this->dispXedocsMessage('msg_not_permitted');
		}

		$oDocumentModel = &getModel('document');
		$document_srl = Context::get('document_srl');
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
		debug_syslog(1, "dispXedocsTreeIndex\n");
		$oXedocsModel = &getModel('xedocs');
		$value = $oXedocsModel->readXedocsTreeCache($this->module_srl);
		//$value = array();		
		Context::set('list', $value);
		$this->setTemplateFile('tree_list');

	}

	function dispXedocsModifyTree()
	{
		if(!$this->grant->write_document) {
				
			return new Object(-1,'msg_not_permitted');
		}

		Context::set('isManageGranted', $this->grant->write_document?'true':'false');
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
