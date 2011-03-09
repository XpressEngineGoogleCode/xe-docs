<?php
class xedocsController extends xedocs {

	function init()
	{
	}




	function procXedocsInsertDocument()
	{

		$oDocumentModel = &getModel('document');


		$oDocumentController = &getController('document');


		if(!$this->grant->write_document) {
			return new Object(-1, 'msg_not_permitted');
		}

		$entry = Context::get('entry');


		$obj = Context::getRequestVars();
		$obj->module_srl = $this->module_srl;

		if($this->module_info->use_comment != 'N'){
			$obj->allow_comment = 'Y';
		} else {
			$obj->allow_comment = 'N';
		}

		if(!$obj->nick_name) {
			$obj->nick_name = "anonymous";
		}

		if($obj->is_notice!='Y'||!$this->grant->manager){
			$obj->is_notice = 'N';
		}

		settype($obj->title, "string");
		if($obj->title == '') {
			$obj->title = cut_str(strip_tags($obj->content),20,'...');
		}

		if($obj->title == '') {
			$obj->title = 'Untitled';
		}


		$oDocument = $oDocumentModel->getDocument($obj->document_srl, $this->grant->manager);


		if( $oDocument->isExists()
		    && $oDocument->document_srl == $obj->document_srl) {

			if($oDocument->get('title')=='Front Page'){
				$obj->title = 'Front Page';
			}

			$output = $oDocumentController->updateDocument($oDocument, $obj);


			if($output->toBool()) {
				$oDocumentController->deleteDocumentAliasByDocument($obj->document_srl);
				$oDocumentController->insertAlias($obj->module_srl, $obj->document_srl, $obj->title);
			}
			$msg_code = 'success_updated';


		} else {
	
			$output = $oDocumentController->insertDocument($obj,false);
			$msg_code = 'success_registed';
			$obj->document_srl = $output->get('document_srl');
			$oDocumentController->insertAlias($obj->module_srl, $obj->document_srl, $obj->title);
		}

		if(!$output->toBool()) return $output;

		$this->recompileTree($this->module_srl);


		$entry = $oDocumentModel->getAlias($output->get('document_srl'));

		$site_module_info = Context::get('site_module_info');
		if($entry) {			
			$url = getSiteUrl($site_module_info->document,'','mid',$this->module_info->mid,'entry',$entry);
		} else {
			$url = getSiteUrl($site_module_info->document,'','document_srl',$output->get('document_srl'));
		}

		$this->setRedirectUrl($url);

		$this->setMessage($msg_code);
	}


	function sendCommentChangeNotification($oDocument, $obj)
	{
		$oMail = new Mail();
		$oMail->setTitle($oDocument->getTitleText());
		$oMail->setContent( sprintf("From : <a href=\"%s#comment_%d\">%s#comment_%d</a><br/>\r\n%s",
		$oDocument->getPermanentUrl(), $obj->comment_srl,
		$oDocument->getPermanentUrl(), $obj->comment_srl,
		$obj->content));

		$oMail->setSender($obj->user_name, $obj->email_address);

		$target_mail = explode(',',$this->module_info->admin_mail);

		for($i=0;$i<count($target_mail);$i++) {
			$email_address = trim($target_mail[$i]);
				
			if(!$email_address) continue;
				
			$oMail->setReceiptor($email_address, $email_address);
			$oMail->send();
		}

	}

	function procXedocsInsertComment() {

		debug_syslog(1, "procXedocsInsertComment\n");
		
		if(!$this->grant->write_comment){
			return new Object(-1, 'msg_not_permitted');
		}


		$obj = Context::gets('document_srl','comment_srl','parent_srl','content','password',
				     'nick_name','nick_name','member_srl','email_address','homepage',
				     'is_secret','notify_message');

		$obj->module_srl = $this->module_srl;


		$oDocumentModel = &getModel('document');
		$oDocument = $oDocumentModel->getDocument($obj->document_srl);

		if(!$oDocument->isExists()) {
			return new Object(-1,'msg_not_permitted');
		}


		$oCommentModel = &getModel('comment');

		$oCommentController = &getController('comment');

		if(!$obj->comment_srl) {
			$obj->comment_srl = getNextSequence();
		} else {
			$comment = $oCommentModel->getComment($obj->comment_srl, $this->grant->manager);
		}


		if($comment->comment_srl != $obj->comment_srl) {


			if($obj->parent_srl) {
				$parent_comment = $oCommentModel->getComment($obj->parent_srl);
				if(!$parent_comment->comment_srl){
					return new Object(-1, 'msg_invalid_request');
				}

				$output = $oCommentController->insertComment($obj);


			} else {
				$output = $oCommentController->insertComment($obj);
			}


			if($output->toBool() && $this->module_info->admin_mail) {
				$this->sendCommentChangeNotification($oDocument, $obj);
			}

		} else {
			$obj->parent_srl = $comment->parent_srl;
			$output = $oCommentController->updateComment($obj, $this->grant->manager);
			$comment_srl = $obj->comment_srl;
		}

		if(!$output->toBool()){
			return $output;
		}

		$this->setMessage('success_registed');
		$this->add('mid', Context::get('mid'));
		$this->add('document_srl', $obj->document_srl);
		$this->add('comment_srl', $obj->comment_srl);
	}

	function procXedocsDeleteDocument()
	{
		$oDocumentController = &getController('document');
		$oDocumentModel = &getModel('document');


		if(!$this->grant->delete_document){
			return new Object(-1, 'msg_not_permitted');
		}

		$document_srl = Context::get('document_srl');

		if(!$document_srl) {
			return new Object(-1,'msg_invalid_request');
		}

		$oDocument = $oDocumentModel->getDocument($document_srl);

		if(!$oDocument || !$oDocument->isExists()) {
			return new Object(-1,'msg_invalid_request');
		}

		if($oDocument->get('title')=='Front Page'){
			return new Object(-1,'msg_invalid_request');
		}

		$output = $oDocumentController->deleteDocument($oDocument->document_srl);

		if(!$output->toBool()){
			return $output;
		}

		$oDocumentController->deleteDocumentAliasByDocument($oDocument->document_srl);

		$this->recompileTree($this->module_srl);

		$tree_args->module_srl = $this->module_srl;
		$tree_args->document_srl = $oDocument->document_srl;
		$output = executeQuery('xedocs.deleteTreeNode', $tree_args);

		$site_module_info = Context::get('site_module_info');
		$this->setRedirectUrl(getSiteUrl($site_module_info->domain,'','mid',$this->module_info->mid));
	}

	function procXedocsDeleteComment()
	{
		// check the comment's sequence number
		$comment_srl = Context::get('comment_srl');
		if(!$comment_srl) {
			return $this->doError('msg_invalid_request');
		}

		// create controller object of comment module
		$oCommentController = &getController('comment');

		$output = $oCommentController->deleteComment($comment_srl, $this->grant->manager);
		if(!$output->toBool()){
			return $output;
		}

		$this->add('mid', Context::get('mid'));
		$this->add('page', Context::get('page'));
		$this->add('document_srl', $output->get('document_srl'));
		$this->setMessage('success_deleted');
	}

	function procXedocsMoveTree() {

		if(!$this->grant->write_document){
			return new Object(-1, 'msg_not_permitted');
		}


		$args = Context::gets('parent_srl','target_srl','source_srl');


		$output = executeQuery('xedocs.getTreeNode', $args);
		$node = $output->data;
		if(!$node->document_srl) {
			return new Object('msg_invalid_request');
		}

		$args->module_srl = $node->module_srl;
		$args->title = $node->title;
		if(!$args->parent_srl){
			$args->parent_srl = 0;
		}

		if(!$args->target_srl) {
			$list_order->parent_srl = $args->parent_srl;
			$output = executeQuery('xedocs.getTreeMinListorder',$list_order);
			if($output->data->list_order) {
				$args->list_order = $output->data->list_order-1;
			}

		} else {
			$t_args->source_srl = $args->target_srl;
			$output = executeQuery('xedocs.getTreeNode', $t_args);
			$target = $output->data;

			$update_args->module_srl = $target->module_srl;
			$update_args->parent_srl = $target->parent_srl;
			$update_args->list_order = $target->list_order;

			if(!$update_args->parent_srl) {
				$update_args->parent_srl = 0;
			}

			$output = executeQuery('xedocs.updateTreeListOrder', $update_args);
			if(!$output->toBool()) {
				return $output;
			}


			/*$restore_args->module_srl = $target->module_srl;
			 $restore_args->source_srl = $target->document_srl;
			 $restore_args->list_order = $target->list_order;
			 $output = executeQuery('xedocs.updateTreeNode', $restore_args);
			 if(!$output->toBool()) return $output;*/

			$args->list_order = $target->list_order+1;
		}

		if(!$node->is_exists) {
			$output = executeQuery('xedocs.insertTreeNode',$args);
		}

		else {
			$output = executeQuery('xedocs.updateTreeNode',$args);
		}

		debugPRint($output);
		if(!$output->toBool()){
			return $output;
		}

		if($args->list_order) {
			$doc->document_srl = $args->source_srl;
			$doc->list_order = $args->list_order;
			$output = executeQuery('xedocs.updateDocumentListOrder', $doc);
			if(!$output->toBool()){
				return $output;
			}
		}

		$this->recompileTree($this->module_srl);
	}

	function procXedocsRecompileTree()
	{
		if(!$this->grant->write_document){
			return new Object(-1,'msg_not_permitted');
		}

		return $this->recompileTree($this->module_srl);
	}

	function recompileTree($module_srl)
	{
		$oXedocsModel = &getModel('xedocs');
		$list = $oXedocsModel->loadXedocsTreeList($module_srl);

		$dat_file = sprintf('%sfiles/cache/xedocs/%d.dat', _XE_PATH_,$module_srl);
		$xml_file = sprintf('%sfiles/cache/xedocs/%d.xml', _XE_PATH_,$module_srl);

		$buff = '';
		$xml_buff = "<root>\n";


		foreach($list as $key => $val) {
			$buff .= sprintf('%d,%d,%d,%d,%s%s',
			$val->parent_srl,$val->document_srl,
			$val->depth,$val->childs,$val->title,"\n");

			$xml_buff .= sprintf('<node node_srl="%d" parent_srl="%d"><![CDATA[%s]]></node>%s',
			$val->document_srl, $val->parent_srl, $val->title,"\n");
		}

		$xml_buff .= '</root>';

		FileHandler::writeFile($dat_file, $buff);
		FileHandler::writeFile($xml_file, $xml_buff);

		return new Object();

	}



}