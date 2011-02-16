<?php

class xedocsModel extends xedocs {

	function init(){
	}

	function getManualList()
	{
		$oModuleModel = &getModel('module');
		$config = $oModuleModel->getModuleConfig('xedocs');
		if( $config->manual_ids ) {
			$this->manual_ids = $config->manual_ids;
		}
	}


	function getXmlCacheFilename($module_srl)
	{
		return sprintf('%sfiles/cache/xedocs/%d.xml', _XE_PATH_, $module_srl);
	}

	function getDatCacheFilename($module_srl)
	{
		return  sprintf('%sfiles/cache/xedocs/%d.dat', _XE_PATH_,$module_srl);
	}


	function getXedocsTreeList()
	{
		$oXedocsController = &getController('xedocs');

		header("Content-Type: text/xml; charset=UTF-8");
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");

		if(!$this->module_srl) {
			return new Object(-1,'msg_invalid_request');
		}

		$xml_file = $this->getXmlCacheFilename($this->module_srl);

		if(!file_exists($xml_file)) {
			$oXedocsController->recompileTree($this->module_srl);
		}

		print FileHandler::readFile($xml_file);
		Context::close();
		exit();
	}

	function readXedocsTreeCache($module_srl)
	{

		$oXedocsController = &getController('xedocs');

		debug_syslog(1, "readXedocsTreeCache module_srl=".$module_srl." \n");

		if(!$module_srl) {
			return new Object(-1,'msg_invalid_request');
		}

		$dat_file = $this->getDatCacheFilename($module_srl);
			
		debug_syslog(1, "readXedocsTreeCache getDatCacheFilename=".$dat_file." \n");

		if(!file_exists($dat_file)) {
			$oXedocsController->recompileTree($module_srl);
		}

		$buff = explode("\n", trim(FileHandler::readFile($dat_file)));
		if(!count($buff)){
			return array();
		}

		foreach($buff as $val) {
			if(!preg_match('/^([0-9]+),([0-9]+),([0-9]+),([0-9]+),(.+)$/i', $val, $m)){
				continue;
			}
			unset($obj);
			$obj->parent_srl = $m[1];
			$obj->document_srl = $m[2];
			$obj->depth = $m[3];
			$obj->childs = $m[4];
			$obj->title = $m[5];
			$list[] = $obj;
		}
		return $list;
	}

	function loadXedocsTreeList($module_srl)
	{

		$args->module_srl = $module_srl;
		$output = executeQueryArray('xedocs.getTreeList', $args);

		if(!$output->data || !$output->toBool()){
			return array();
		}

		$list = array();
		$root_node = null;
		foreach($output->data as $node) {
			if($node->title == 'Front Page') {
				$root_node = $node;
				$root_node->parent_srl = 0;
				continue;
			}
			unset($obj);
			$obj->parent_srl = (int)$node->parent_srl;
			$obj->document_srl = (int)$node->document_srl;
			$obj->title = $node->title;
			$list[$obj->document_srl] = $obj;
		}

		$tree[$root_node->document_srl]->node = $root_node;

		foreach($list as $document_srl => $node) {
			if(!$list[$node->parent_srl]) {
				$node->parent_srl = $root_node->document_srl;
			}
			$tree[$node->parent_srl]->childs[$document_srl] = &$tree[$document_srl];
			$tree[$document_srl]->node = $node;
		}

		$result[$root_node->document_srl] = $tree[$root_node->document_srl]->node;
		$result[$root_node->document_srl]->childs = count($tree[$root_node->document_srl]->childs);

		$this->getTreeToList($tree[$root_node->document_srl]->childs, $result,1);

		return $result;
	}

	function getPrevNextDocument($module_srl, $document_srl)
	{
		$list = $this->readXedocsTreeCache($module_srl);
		if(!count($list)){
			return array(0,0);
		}

		$prev = $next_srl = $prev_srl = 0;
		$checked = false;

		foreach($list as $key => $val) {
			if($checked) {
				$next_srl = $val->document_srl;
				break;
			}
			if($val->document_srl == $document_srl) {
				$prev_srl = $prev;
				$checked = true;
			}
			$prev = $val->document_srl;
		}

		return array($prev_srl, $next_srl);
	}

	function getTreeToList($childs, &$result,$depth)
	{
		if(!count($childs)){
			return;
		}

		foreach($childs as $key => $node) {

			$node->node->depth = $depth;
			$node->node->childs = count($node->childs);
			$result[$key] = $node->node;

			if($node->childs){
				$this->getTreeToList($node->childs, $result,$depth+1);
			}
		}
	}

	function getContributors($document_srl)
	{
		$oDocumentModel = &getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);
		if(!$oDocument->isExists()) {
			return array();
		}

		$args->document_srl = $document_srl;
		$output = executeQueryArray("xedocs.getContributors", $args);

		if($output->data) {
			$list = $output->data;
		} else {
			$list = array();
		}

		$item->member_srl = $oDocument->getMemberSrl();
		$item->nick_name = $oDocument->getNickName();
		$contributors[] = $item;

		for($i=0,$c=count($list); $i<$c; $i++) {
			unset($item);
			$item->member_srl = $list[$i]->member_srl;
			$item->nick_name = $list[$i]->nick_name;

			if($item->member_srl == $oDocument->getMemberSrl()) {
				continue;
			}
			$contributors[] = $item;
		}

		return $contributors;
	}

	function make_parents_links( $parents , $module_srl)
	{
		$site_module_info = Context::get('site_module_info');
		
		foreach($parents as $parent){
			$url = getSiteUrl($site_module_info->document, '', 'document_srl',$parent->document_srl);
			$parent->{'href'} = $url;	
		}
	}
	
	function getParents($document_srl, $module_srl)
	{
		$parents = $this->buildParents($document_srl, $module_srl);
		$this->make_parents_links($parents, $module_srl);
		return $parents;
	}
	
	
	function buildParents($document_srl, $module_srl)
	{

		//get all nodes
		$args->module_srl = $module_srl;
		$output = executeQueryArray('xedocs.getTreeList', $args);

		if(!$output->data || !$output->toBool()){
			return array();
		}

		//get document and parent
		$list = array();
		$root = null;
		foreach($output->data as $node) {
				
			unset($obj);
			$obj->document_srl = (int)$node->document_srl;
			$obj->title = $node->title;

//			if($node->title == 'Introduction to CUBRID'){
//				$obj->parent_srl =(int)$node->parent_srl;
//				$root = $obj;
//			}
			if($node->title == 'Front Page') {
				$obj->parent_srl = 0;
				$list[$obj->document_srl] = $obj;
				continue;
			}else{
				$obj->parent_srl = (int)$node->parent_srl;
				$list[$obj->document_srl] = $obj;
			}
		}
		//get parents for document_srl
		$result = array();
		$doc_srl = $document_srl;

		while($doc_srl){
			$result[] = $list[$doc_srl];//add parent_srl
			$doc_srl = $list[$doc_srl]->parent_srl;//check for parent_srl
		}
			
//		$result[] = $root;
//		
//	$list2[] = $this->loadXedocsTreeCache($module_srl);
//		//get parents for document_srl
//		$result = array();
//		$doc_srl = $document_srl;
//
//		foreach($list as $node){
//			if($doc_srl == $node->docment_srl){
//				$result[] = $list[$doc_srl];//add parent_srl
//			}
//		}
//		while(0 < $doc_srl){
//			$result[] = $list[$doc_srl];//add parent_srl
//			$doc_srl = $list[$doc_srl]->parent_srl;//check for parent_srl
//		}
//			

		return $result;
	}
}
?>
