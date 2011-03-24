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

		if(!$module_srl) {
			return new Object(-1,'msg_invalid_request');
		}

		$dat_file = $this->getDatCacheFilename($module_srl);
			
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

	function make_links( $nodes , $module_srl)
	{
		$site_module_info = Context::get('site_module_info');

		$oDocumentModel = &getModel('document');
		$oModuleModel = &getModel('module');
		$site_module_info = Context::get('site_module_info');

		foreach($nodes as $node){
			$module_info = $oModuleModel->getModuleInfoByDocumentSrl($node->document_srl);
			
			$entry = $oDocumentModel->getAlias($node->document_srl);
			
			if( $entry ){
				
				$url = getSiteUrl($site_module_info->document,'','mid',$module_info->mid,'entry',$entry);
				
			}else
			{
				$url = getSiteUrl($site_module_info->document, '', 'mid', $module_info->mid, 'document_srl',$node->document_srl);
			}
			$node->{'href'} = $url;
		}
	}

	function getParents($document_srl, $module_srl)
	{
		$parents = $this->buildParents($document_srl, $module_srl);
		$this->make_links($parents, $module_srl);
		return $parents;
	}

	function getChildren($document_srl, $module_srl){
		$children = $this-> buildChildren($document_srl, $module_srl);
		$this->make_links($children, $module_srl);

		return $children;
	}

	function buildChildren($document_srl, $module_srl){
		$list[] = $this->readXedocsTreeCache($module_srl);

		$result = array();
		$home = $this->getHomeNode($list);
		if($home->document_srl == $document_srl){
			$result = $this->getHomeChildrenNode($list,$document_srl);
			return $result;
		}
		
		foreach($list as $node => $ns){
			foreach($ns as $n =>$val){
				
				if($document_srl == $val->parent_srl){
					unset($obj);
					$obj->parent_srl =  $val->parent_srl;
					$obj->document_srl = $val->document_srl;
					$obj->title = $val->title;
					$result[] =  $obj;
				}
			}
		}

		return $result;
	}

	function getHomeChildrenNode($list, $document_srl){
		
		foreach($list as $node => $ns){
			foreach($ns as $n =>$val){
				if(0 == $val->parent_srl && $val->document_srl != $document_srl){
					unset($obj);
					$obj->parent_srl =  $val->parent_srl;
					$obj->document_srl = $val->document_srl;
					$obj->title = $val->title;
					$result[] =  $obj;
				}
			}

		}
		return $result;
	}
	
	function buildParents($document_srl, $module_srl)
	{

		//get parents for document_srl
		$result = array();
		$doc_srl = $document_srl;
		//get all nodes in a list
		$list[] = $this->readXedocsTreeCache($module_srl);

		while(0 < $doc_srl){
			$node = $this->getNode($list, $doc_srl);
			if($node->title == 'Introduction to CUBRID Manual'){
				
			}else{
				$result[] = $node;//add parent_srl
			}
			$doc_srl = $node->parent_srl;//check for parent_srl
		}
		$result[] = $this->getHomeNode($list);
		return $result;
	}

	function getHomeNode($list){
		foreach($list as $node => $ns){
			foreach($ns as $n =>$val){

				if($val->title == 'Introduction to CUBRID Manual'){
					unset($obj);
					$obj->parent_srl =  0;
					$obj->document_srl = $val->document_srl;
					$obj->title = $val->title;
					return $obj;
				}

			}

		}
	}

	function getNode($list, $doc_srl)
	{
		foreach($list as $node => $ns){
			foreach($ns as $n =>$val){

				$document_srl = $val->document_srl;
				if($doc_srl == $document_srl){

					unset($obj);
					$obj->parent_srl =  $val->parent_srl;
					$obj->document_srl = $document_srl;
					$obj->title = $val->title;
					return $obj;
				}
			}

		}
	}




	function getModuleList()
	{
		$args->sort_index = "module_srl";
		$args->page = 1;
		$args->list_count = 200;
		$args->page_count = 10;
		$args->s_module_category_srl = Context::get('module_category_srl');

		$output = executeQueryArray('xedocs.getManualList', $args);
		ModuleModel::syncModuleToSite($output->data);
		return  $output->data;

	}

	function getModulesWithSet($set_id, $module_list)
	{
		$oModuleModel = &getModel('module');

		$modulel_set = array();
		foreach($module_list as $module)
		{
			$module_srls=array();
			$module_srls[] =  $module->module_srl;
				
			$extra_vars = $oModuleModel->getModuleExtraVars($module_srls);
				
			if( 0 == strcmp($set_id, $extra_vars[$module->module_srl]->help_name))
			{
				$module_set[] = $module;
			}
				
		}
		return $module_set;
	}

	/* given a manual set identifier as string compute a list of module_srl
	 *
	 * */

	function getModuleSet($set_id)
	{
		$module_list = $this->getModuleList();

		$module_set = $this->getModulesWithSet($set_id, $module_list);
		$manual_set = array();
		foreach($module_set as $module){
			$manual_set[] = $module->module_srl;
		}
		return $manual_set;
	}

	function getModuleMidSet($set_id)
	{
		$module_list = $this->getModuleList();

		$module_set = $this->getModulesWithSet($set_id, $module_list);
		$mid_set = array();
		foreach($module_set as $module){
			$mid_set[] = $module->mid;
		}
		return $mid_set;
	}




	function getManualVersions($manual_set)
	{
		$oModuleModel = &getModel('module');
		$extra_vars = $oModuleModel->getModuleExtraVars($manual_set);

		$manual_versions = array();
		foreach($manual_set as $module_srl)
		{
			$manual_versions[] =  $extra_vars[$module_srl]->version_label;
		}
		debug_syslog(1, "manual_versions:".print_r($manual_versions, true)."\n");
		return $manual_versions;
	}


	function test_extraversions()
	{
		$manual_srl = 53758;
		$docs = $this->getDocumentList($manual_srl);
		foreach($docs as $doc){
			$versions = $this->get_versions($manual_srl, $doc);
			debug_syslog(1, "get_versions for ".$doc->document_srl. " : ". $versions);
				
			$this->add_version($manual_srl, $doc, "3.1", 1024);
				
			$versions = $this->get_versions($manual_srl, $doc);
			debug_syslog(1, "get_versions for ".$doc->document_srl. " : ". $versions);
				
			$this->add_version($manual_srl, $doc, "3.1", 1024);
				
			$versions = $this->get_versions($manual_srl, $doc);
			debug_syslog(1, "get_versions for ".$doc->document_srl. " : ". $versions);
				
			$this->clear_version($manual_srl, $doc);
				
			$versions = $this->get_versions($manual_srl, $doc);
			debug_syslog(1, "get_versions for ".$doc->document_srl. " : ". $versions);
				
			break;
		}
	}


	function add_version($module_srl, $doc, $version, $version_doc_srl)
	{

		$document_versions = $this->get_versions($module_srl, $doc);

		$insert = (0 == strcmp('', trim($document_versions)));

		debug_syslog(1, "add_version document_srl=".$doc->document_srl." title |".$doc->getTitle()."|\n");
		debug_syslog(1, "            version=".$version." version_doc_srl=".$version_doc_srl."\n");

		$args->document_srl = $doc->document_srl;
		$args->module_srl = $module_srl;

		$args->eid = "version_labels";

		if(0 != strcmp('', trim($document_versions))){
			$args->value = $document_versions."|".$version."->".$version_doc_srl;
		}else{
			$args->value = "".$version."->".$version_doc_srl;
		}

		if($insert){
			$output = executeQuery('xedocs.insertDocumentExtraVars', $args);
			//debug_syslog(1, "add: insert: ".print_r($output, true));
		}else{
			$output = executeQuery('xedocs.updateDocumentExtraVars', $args);
			//debug_syslog(1, "add: update: ".print_r($output, true));
		}

	}


	function get_versions($module_srl, $doc)
	{
		$args->{'document_srl'} = $doc->document_srl;
		$args->{'module_srl'} = $module_srl;
		$args->{'eid'} = "version_labels";

		$output =  executeQuery('xedocs.getDocumentExtraVars', $args);

		//debug_syslog(1, "get_versios: ".print_r($output, true));
		if (isset($output->data)){
			//debug_syslog(1, "get_versios value: ".print_r($output->data->value, true));
			return $output->data->value;
		}

		return "";

	}

	function clear_version($module_srl, $doc)
	{

		$args->document_srl = $doc->document_srl;
		$args->module_srl = $module_srl;

		$args->eid = "version_labels";

		$output =  executeQuery('xedocs.deleteDocumentExtraVars', $args);

		//debug_syslog(1, "delete_versios: ".print_r($output, true));

	}

	function getDocumentList($module_srl)
	{

		$oDocumentModel = &getModel('document');
		$obj->module_srl = $module_srl;
		$obj->sort_index = 'update_order';

		$obj->search_keyword = "";
		$obj->search_target = "";
		$obj->page = 1;
		$obj->list_count = 50000;


		$output = $oDocumentModel->getDocumentList($obj);

		return $output->data;
	}
	
	
	function string_to_meta($metastring)
	{
		 
		$meta = array();
		$values = explode( "|", $metastring);
		
		foreach($values as $v)
		{
		
			$m = explode( ",", $v);
		
			if(2 == count($m) ){
				$obj = array();
				$obj['name'] = $m[0];
				$obj['value'] = $m[1];
		
				$meta[] = $obj;
			}
		}
		
		return $meta;
	}
	
	
	function meta_to_string($meta)
	{
		
		$values = array();
		foreach($meta as $val)
		{
			$values[] = implode(array($val['name'], $val['value']), ",");
			 		
		}
		return implode($values, "|"); 
	}
	

		
	function add_meta($module_srl, $document_srl, $meta)
	{
		

		if(0 != count($meta))
		{
			$args = null;
			$args->document_srl = $document_srl;
			$args->module_srl = $module_srl;
			$args->eid = "meta";
			
			$args->value = $this->meta_to_string($meta);
			debug_syslog(1, "add_meta value: ".$args->value ."\n");
			$output = executeQuery('xedocs.insertDocumentExtraVars', $args);
			debug_syslog(1, "add_meta result: ".print_r(0==$output->error, true)."\n");
			return 0 == $output->error;
			
		}else{
			debug_syslog(1, "no meta to add\n");
		}
		return false;
	}
	
	function get_meta($module_srl, $document_srl)
	{
		$args = null;
		$args->{'document_srl'} = $document_srl;
		$args->{'module_srl'} = $module_srl;
		$args->{'eid'} = "meta";

		$output =  executeQuery('xedocs.getDocumentExtraVars', $args);

		
		if (isset($output->data)){
			
			syslog(1, "get_meta value: ".print_r($output->data->value, true));
			return $this->string_to_meta($output->data->value);
		}

		return array();

	}

}
?>
