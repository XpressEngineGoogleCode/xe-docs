<?php

class xedocsModel extends xedocs {

        var $manual_ids = array();

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

	function get_first_node_srl($module_srl)
	{
		if(!isset($module_srl)) return null;


		$oModuleModel = &getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);

		if( !isset($module_info) ) return null;

		if(!isset($module_info->first_node_srl))
		{
			$value = $this->readXedocsTreeCache($module_srl);
                        if(!$value) return;
			foreach( $value as $i=>$obj){
				$document_srl = $obj->document_srl;
				break;
			}
		}else{
			$document_srl = $module_info->first_node_srl;
        	}

		return $document_srl;
	}


	function check_document_srl($document_srl, $expected_module_info){
		$args->document_srl = $document_srl;
        $output = executeQuery('document.getDocument', $args);


        ////debug_syslog(1, "check_document_srl(".$document_srl.")->".print_r($output, true)."\n");

        if (!$output->toBool()) return false;


        $oModuleModel = &getModel('module');
        $module_info = $oModuleModel->getModuleInfoByDocumentSrl($document_srl);

        if( !isset($module_info)){
        	return false;
        }
        ////debug_syslog(1, "check_document_srl(".$document_srl.") module_info".print_r($module_info, true)."\n");

        if($expected_module_info->mid != $module_info->mid){
        	//debug_syslog(1, "mid mismatch: expected=".$expected_module_info->mid."vs actual=".$module_info->mid."\n");
        	return false;
        }

        return true;

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
			$oXedocsController->_recompileTree($this->module_srl);
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
			$oXedocsController->_recompileTree($module_srl);
		}

		$buff = explode("\n", trim(FileHandler::readFile($dat_file)));
		if(!count($buff)){
			return array();
		}

                $list = array();
		foreach($buff as $val) {
			if(!preg_match('/^([0-9]+),([0-9]+),([0-9]+),([0-9]+),(.+),(.+)$/i', $val, $m)){
				continue;
			}
			unset($obj);
			$obj->parent_srl = $m[1];
			$obj->document_srl = $m[2];
			$obj->depth = $m[3];
			$obj->childs = $m[4];
			$obj->alias = $m[5];
                        $obj->title = $m[6];
			$list[$obj->document_srl] = $obj;
		}
		return $list;
	}

        /**
         * Reads document hierarchy from database and converts it to
         * a serializable list (used for caching)
         */
	function loadXedocsTreeList($module_srl)
	{

		$args->module_srl = $module_srl;
		$output = executeQueryArray('xedocs.getTreeList', $args);
		if(!$output->data || !$output->toBool()){
			return array();
		}

		$list = array();
		$root_nodes = array();
		foreach($output->data as $node) {
                    if($node->parent_srl == 0) {
                        unset($root_node);
                        $root_node = $node;
                        $root_node->parent_srl = 0;
                        $root_nodes[$node->document_srl] = $root_node;
                        continue;
                    }
                    unset($obj);
                    $obj->parent_srl = (int)$node->parent_srl;
                    $obj->document_srl = (int)$node->document_srl;
                    $obj->title = $node->title;
                    $obj->alias = $node->alias;
                    $list[$obj->document_srl] = $obj;
		}

                foreach($root_nodes as $root_node) {
                    $tree[$root_node->document_srl]->node = $root_node;
                }

		foreach($list as $document_srl => $node) {
			if(!$list[$node->parent_srl] && !$root_nodes[$node->parent_srl]) {
				$node->parent_srl = 0;
			}
			$tree[$node->parent_srl]->childs[$document_srl] = &$tree[$document_srl];
			$tree[$document_srl]->node = $node;
		}

                foreach($root_nodes as $root_node) {
                    $result[$root_node->document_srl] = $tree[$root_node->document_srl]->node;
                    $result[$root_node->document_srl]->childs = count($tree[$root_node->document_srl]->childs);
                    $this->getTreeToList($tree[$root_node->document_srl]->childs, $result,1);
                }

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

	function make_document_link($document_srl)
	{
			$oDocumentModel = &getModel('document');
			$oModuleModel = &getModel('module');
			$site_module_info = Context::get('site_module_info');


			$module_info = $oModuleModel->getModuleInfoByDocumentSrl($document_srl);

			$entry = $oDocumentModel->getAlias($document_srl);

			if( $entry ){

				$url = getSiteUrl($site_module_info->document,'','mid',$module_info->mid,'entry',$entry);

			}else
			{

				$url = getSiteUrl($site_module_info->document, '', 'mid', $module_info->mid, 'document_srl',$document_srl);
			}
			return $url;
	}

	function getParentSrl($document_srl, $module_srl, $list){

		$parent_srl;
		foreach($list as $node => $ns){
			foreach($ns as $n =>$val){

				if($document_srl == $val->document_srl){
					$parent_srl = $val->parent_srl;
					return $parent_srl;
				}
			}
		}

		return $parent_srl;
	}

        function getModuleList($add_extravars = false)
	{
		$args->sort_index = "module_srl";
		$args->page = 1;
		$args->list_count = 200;
		$args->page_count = 10;
		$args->s_module_category_srl = Context::get('module_category_srl');

		$output = executeQueryArray('xedocs.getManualList', $args);
		ModuleModel::syncModuleToSite($output->data);

		if(!$add_extravars){
			return  $output->data;
		}

		$oModuleModel = &getModel('module');

		foreach($output->data as $module_info){
			$extra_vars = $oModuleModel->getModuleExtraVars($module_info->module_srl);
			foreach($extra_vars[$module_info->module_srl] as $k=>$v){
				$module_info->{$k} = $v;
			}
		}

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

        /**
         * Gets all versions of a document
         * Mapping is done based on alias - if there are more documents with
         * the same alias, but belonging to different manuals from the same
         * manual set, they are considered versions of the same page.
         *
         * Input: manual_set, document_alias
         * Ouput: array with: document_srl, document_alias, module_srl, mid, version_name
         */
	function getVersions($manual_set, $document_alias)
	{
            $args = null;
            $args->manual_set = $manual_set;
            $args->alias = $document_alias;
            $output = executeQueryArray('xedocs.getDocumentVersions', $args);
            if(!$output->toBool() || !$output->data) return array();
            return $output->data;
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
			$args->var_idx = 0;

			$args->value = $this->meta_to_string($meta);

			$output = executeQuery('xedocs.insertDocumentExtraVars', $args);

			return 0 == $output->error;

		}else{
			//debug_syslog(1, "no meta to add\n");
		}
		return false;
	}

	function get_meta($module_srl, $document_srl)
	{
		$args = null;
		$args->document_srl = $document_srl;
		$args->module_srl = $module_srl;
		$args->eid = "meta";
		$args->var_idx = 0;
		$output =  executeQuery('xedocs.getDocumentExtraVars', $args);


		if (isset($output->data)){

			return $this->string_to_meta($output->data->value);
		}

		return array();

	}


	function add_original_url($document_srl, $url)
	{
			$args = null;
			$args->document_srl = $document_srl;
			$args->module_srl = $module_srl;
			$args->eid = "orig_url";
			$args->var_idx = 0;

			$args->value = $url;

			$output = executeQuery('xedocs.insertDocumentExtraVars', $args);

			return 0 == $output->error;

	}

	function get_original_url($document_srl)
	{
		$args = null;
		$args->document_srl = $document_srl;
		$args->module_srl = $module_srl;
		$args->eid = "orig_url";
		$args->var_idx = 0;

		$output =  executeQuery('xedocs.getDocumentExtraVars', $args);

		if (isset($output->data)){

			return $output->data->value;
		}

		return "";
	}


	function get_word_count($keyword)
	{
		$values = preg_split("/[\s,]+/", $keyword, -1, PREG_SPLIT_NO_EMPTY);	//any number of spaces or commas
		return count($values);
	}

	function getKeywordTargets($document_list, $max_count=50)
	{
		$keywords = array();
		$oDocumentModel = &getModel('document');
		$count =0;
		$titles = array();
		foreach($document_list as $doc)
		{
			$title = $doc->getTitle();
			$wc = $this->get_word_count($title);

			if( 1 != $wc ) continue;
			$obj = null;
			$obj->title = $title;
			$obj->target_document_srl = $doc->document_srl;

			$oldk = $titles[$title];
			if(!isset($oldk)){
				$titles[$title] = array($obj);
			}else{
				$obj->title = $oDocumentModel->getAlias($document_srl);
				$oldk[] = $obj;
			}

			$keywords[] = $obj;
			$count += 1;
			if($count > $max_count) break;
		}

		return $keywords;
	}

	function keyword_to_string($key){
		$result = array();
		foreach($key as $name=>$val){
			$result[] = $name."=".$val;
		}
		return implode(",", $result);
	}

	function string_to_keyword($value)
	{

		if( !isset($value) || 0==strcmp("", $value)){
			return null;
		}
		$key = null;
		$result = explode(",", $value);
		foreach($result as $val){
			$values = explode("=", $val);
			$key->{$values[0]} = $values[1];
		}
		return $key;
	}

	function keyword_list_to_string($keywords){
		$k = array();
		foreach($keywords as $key){
			$obj = $this->keyword_to_string($key);
			if(isset($obj)){
				$k[] = $obj;
			}
		}
		return implode("|-|", $k);
	}

	function string_to_keyword_list($value, $filter_keyword = null){

		$skeywords = explode("|-|", $value);
		$keywords = array();
		foreach($skeywords as $sval){
			$kval = $this->string_to_keyword($sval);

			if(isset($filter_keyword))
			{
				$m = array();
				if(!preg_match("#".$filter_keyword."#", $kval->title, $m)){
					continue;
				}
			}
			$keywords[] = $kval;
		}
		return $keywords;
	}

	function make_keyword_link($key, $link_id, $color){
		$url = $this->make_document_link($key->target_document_srl);

		$result->href =  "<a id='".$link_id."' style='background-color:".$color.";' href='".$url."'>".$key->title."</a>";
		$result->url = $url;

		return $result;
	}

        /**
         * @brief Constructs an URL for accesing a given document by alias
         */
        function getDocumentURL($document_srl){
            $oDocumentModel = &getModel('document');
            $oModuleModel = &getModel('module');

            $module_info = $oModuleModel->getModuleInfoByDocumentSrl($document_srl);
            $entry = $oDocumentModel->getAlias($document_srl);

            return getUrl('mid',$module_info->mid
                        , 'entry',$entry
                        , 'module_srl', ''
                        , 'module', ''
                        , 'act', ''
                        , '_filter', ''
                        , 'title', ''
                        , 'target_document_srl', '');
        }

        /**
         * @brief Constructs an HTML anchor which will point towards
         * a given document and whose text will be the given keyword
         */
        function getKeywordLink($document_srl, $keyword, $url = null){
            if(!isset($url))
                $url = $this->getDocumentURL($document_srl);
            return "<a id='keyword_$document_srl' class='keyword_link' href='$url'>$keyword</a>";
        }

        /**
         * @brief Replaces a set of keywords with hyperlinks
         *
         * This methods uses simple_html_dom library in order to search
         * for the keywords only in document text and not in HTML markup
         *
         * Example:
         * keyword: world, url: index.php?page=about-the-world
         * Input:
         * <<< HTML
         *  <h2> Hello world! </h2>
         *  It sure is fun going around the world.
         *  <a href="/goodnight-world>Say Good night!</a>
         *  <a href="/goodmorning-world>Say Good morning!</a>
         * HTML;
         * Output:
         * <<< HTML
         *  <h2> Hello <a href="index.php?page=about-the-world">world</a>! </h2>
         *  It sure is fun going around the <a href="index.php?page=about-the-world">world</a>.
         *  <a href="/goodnight-world>Say Good night!</a>
         *  <a href="/goodmorning-world>Say Good morning!</a>
         * HTML;*
         *
         * Reference: http://stackoverflow.com/questions/3151064/find-and-replace-keywords-by-hyperlinks-in-an-html-fragment-via-php-dom
         */
	function replaceKeywordsWithLinks($content, $keywords)
	{
            $keyword_frequency = array();
            $keyword_replacement = array();

            // Replace keywords with links and save replacements made in an array
            $dom = new DOMDocument;
            $dom->formatOutput = TRUE;
            $dom->loadHTML($content);
            $xpath = new DOMXPath($dom);

            foreach($keywords as $keyword){
                unset($nodes);
                $nodes = $xpath->query('//text()[contains(., "' . $keyword->title .'")]');
                foreach($nodes as $node) {
                    $link     = $this->getKeywordLink($keyword->target_document_srl, $keyword->title, $keyword->url);
                    $replaced = str_replace($keyword->title, $link, $node->wholeText);
                    $keyword_frequency[$keyword->title]++;
                    $keyword_replacement[$keyword->title] = $link;
                    $newNode  = $dom->createDocumentFragment();
                    $newNode->appendXML($replaced);
                    $node->parentNode->replaceChild($newNode, $node);
                }
            }

            $content = $dom->saveHTML($dom->documentElement);;

            // Add a See also section at the end of the document, based on previous replacements made
            $links = array();
            foreach($keyword_frequency as $keyword => $frequency){
                $links[$frequency][] = $keyword_replacement[$keyword];
            }

            if(count($links) > 0){
                $see_also_content = '<h4> See Also:</h4><ul>';
                foreach($links as $link_group){
                    foreach($link_group as $link) $see_also_content .= "<li>" . $link . "</li>";
                }
                $see_also_content .= '</ul>';
            }

            return $content . $see_also_content;
	}

	function update_keyword($module_srl, $orig_keyword, $keyword, $target_document_srl)
	{
		if(!isset($keyword) || !isset($keyword) || !isset($target_document_srl)){
			return false;
		}

		$oModuleModel = &getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		if(!isset($module_info) ){
			return false;
		}


		$keywords = null;
		if( isset($module_info->keywords) )
		{
			$keywords = $this->string_to_keyword_list($module_info->keywords);
		}
		//remove original
		if( isset($orig_keyword))
		{
			//debug_syslog(1, "there are ".count($keywords)." keywords\n");
			foreach($keywords as $i=>$val){
				if(0 == strcmp($val->title,$orig_keyword) ){
					$val->title = $keyword;
					$val->target_document_srl = $target_document_srl;
					break;
				}
			}
		}else{
			//debug_syslog(1, "adding keyword\n");
			$obj = null;
			$obj->title = $keyword;
			$obj->target_document_srl = $target_document_srl;

			$keywords[] = $obj;
		}

		//debug_syslog(1, "updating extravars\n");

		$extra_vars = $oModuleModel->getModuleExtraVars($module_srl);
		$update_args = $extra_vars[$module_srl];
		$update_args->{'keywords'} = $this->keyword_list_to_string($keywords);

		$oModuleController = &getController('module');
		$oModuleController->insertModuleExtraVars($module_srl, $update_args);

		return true;

	}

	function delete_keyword($module_srl, $keyword)
	{
		if(!isset($keyword)){
			return false;
		}

		$oModuleModel = &getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		if(!isset($module_info) || !isset($module_info->keywords)){
			return false;
		}

		$keywords = $this->string_to_keyword_list($module_info->keywords);
		$deleted = false;
		//debug_syslog(1, "there are ".count($keywords)." keywords\n");
		foreach($keywords as $i=>$val){
			if(0 == strcmp($val->title,$keyword) ){
				unset($keywords[$i]);
				$deleted = true;
				break;
			}
		}

		if($deleted)
		{

			//debug_syslog(1, "keyword deleted. updating extravars\n");

			$extra_vars = $oModuleModel->getModuleExtraVars($module_srl);
			$update_args = $extra_vars[$module_srl];
			$update_args->{'keywords'} = $this->keyword_list_to_string($keywords);

			$oModuleController = &getController('module');
			$oModuleController->insertModuleExtraVars($module_srl, $update_args);
		}
		return $deleted;
	}

	function _is_search($is_keyword, $target_module_srl, $search_target, $page, $items_per_page= 10)
	{
		$oDocumentModel = &getModel('document');

		$obj = null;
		$obj->module_srl =array($target_module_srl);

		$obj->page = $page;
		$obj->list_count = $items_per_page;

		$obj->exclude_module_srl = '0';
		$obj->sort_index = 'module';
		//$obj->order_type = 'asc';
		$obj->search_keyword = $is_keyword;
		$obj->search_target = $search_target;
		return $oDocumentModel->getDocumentList($obj);

	}

	function _lucene_search($is_keyword, $target_module_srl, $search_target, $page, $items_per_page= 10 )
	{
		$oLuceneModel = &getModel('xedocs'); //temporary imported sources so we not interfere with nlucene

		//debug_syslog(1, "_lucene_search search target_module_srl=".$target_module_srl."\n");
		$searchAPI = "lucene_search_bloc-1.0/SearchBO/";

		$searchUrl = $oLuceneModel->getDefaultSearchUrl($searchAPI);
		//debug_syslog(1, "searchUrl=".$searchUrl."\n");

		//debug_syslog(1, "setup complete.target_mid=".$target_module_srl." now checking\n");

		if(!$oLuceneModel->isFieldCorrect($search_target)){
		  $search_target = 'title_content';
		}
		//debug_syslog(1, "search_target=".$search_target."\n");

		//Search queries applied to the target module
		$query = $oLuceneModel->getSubquery($target_module_srl, "include", null);

		//debug_syslog(1, "subquery=".$query."\n");
		//Parameter setting
		$json_obj->query = $oLuceneModel->getQuery($is_keyword, $search_target, null);
		$json_obj->curPage = $page;
		$json_obj->numPerPage = $items_per_page;
		$json_obj->indexType = "db";
		$json_obj->fieldName = $search_target;
		$json_obj->target_mid = $target_module_srl;
		$json_obj->target_mode = $target_mode;

		$json_obj->subquery = $query;

		return $oLuceneModel->getDocuments($searchUrl, $json_obj);
	}

	function search($is_keyword, $target_module_srl, $search_target, $page, $items_per_page= 10)
	{
		$oLuceneModule = &getModule('lucene');

		if( !isset($oLuceneModule) ){
			//if nlucene not installed we fallback to IS
			return $this->_is_search($is_keyword, $target_module_srl, $search_target, $page, $items_per_page);
		}

		return $this->_lucene_search($is_keyword, $target_module_srl, $search_target, $page, $items_per_page);
	}

	/* lucene search related */
	var $json_service = null;



	function getService(){
	  require_once(_XE_PATH_.'modules/lucene/lib/jsonphp.php');
		if( !isset($this->json_service) ){
			//debug_syslog(1, "creating new json_service\n");
			$this->json_service = new Services_JSON(0);
		}else{
			//debug_syslog(1, "reusing json_service\n");
		}
		return $this->json_service;

	}



        /**
         * @brief 검색 대상 필드를 확인
	          Check the Search for field
         */
        function isFieldCorrect($fieldname) {
            $fields = array('title', 'content', 'title_content', 'tags');
            $answer = in_array($fieldname, $fields);
            return $answer;
        }

        /**
         * @brief module_srl 리스트 및 포함/제외 여부에 따른 조건절을 만듬.
	                     List and include / exclude based on whether the clause making.
         */
        function getSubquery($target_mid, $target_mode, $exclude_module_srl=NULL) {
	  if( isset($exclude_module_srl) ){
            $no_secret = ' AND NOT is_secret:yes AND NOT module_srl:'.$exclude_module_srl."; ";
	  }else{
	    	$no_secret = ' AND NOT is_secret:yes ';
	  }
            $target_mid = trim($target_mid);
            if ('' == $target_mid) return $no_secret;

            $target_mid_list = explode(',', $target_mid);
            $connective = strcmp('include', $target_mode) ? ' AND NOT ':' AND ';

            $query = $no_secret.$connective.'(module_srl:'.implode(' OR module_srl:', $target_mid_list).')';
            return $query;
        }

        /**
         * @brief 검색어에서 nLucene 쿼리 문법을 적용
	           Results for query syntax to apply the nLucene
         */
        function getQuery($query, $search_target, $exclude_module_srl='0') {
            $query_arr = explode(' ', $query);
            $answer = '';

            if ($search_target == "title_content") {
	      return $this->getQuery($query, "title", $exclude_module_srl).$this->getQuery($query, "content", $exclude_module_srl);
            } else {
                foreach ($query_arr as $val) {
                    $answer .= $search_target.':'.$val.' ';
                }
            }
            return $answer;
        }


        /**
         * @brief 검색 결과에서 id의 배열을 추출
	          Results extracted from an array of id
         */
        function result2idArray($res) {
            //$res = $this->getService()->decode($res);
            $res = json_decode($res);
            $results = $res->results;
            $answer = array();
            if ( count($results) > 0) {
                foreach ($results as $result) {
                    $answer[] = $result->id;
                }
            }
            return $answer;
       }



        /**
         * @brief 댓글의 id목록으로 댓글을 가져옴
	          Bringing the id list Comment Comment
         */
        function getComments($searchUrl, $params, $service_prefix = null) {

		if( !isset($service_prefix) ){
			$service_prefix = $this->getDefaultServicePrefix();
		}


            $params->serviceName = $service_prefix.'_comment';
            $params->fieldName = 'content';
            $params->displayFields = array('id');
            $params->query = $params->query.$params->subquery;


            $oModelComment = &getModel('comment');
            $encodedParams = $this->getService()->encode($params);

            $searchResult = FileHandler::getRemoteResource($searchUrl."searchByMap", $encodedParams, 3, "POST", "application/json; charset=UTF-8", array(), array());

            if(!$searchResult && $searchResult != "null") {
                $idList = array();
            } else {
                $idList = $this->result2idArray($searchResult);
            }

            $comments = array();
            if (count($idList) > 0) {
                $tmpComments = $oModelComment->getComments($idList);

                foreach($idList as $id) {
                    $comments['com'.$id] = $tmpComments[$id];
                }
            }

            $searchResult = $this->getService()->decode($searchResult);
            $page_navigation = new PageHandler($searchResult->totalSize, floor(($searchResult->totalSize) / 10+1), $params->curPage, 10);

            $output->total_count = $searchResult->totalSize;
            $output->data = $comments;
            $output->page_navigation = $page_navigation;

           return $output;
        }



        /*
         * Clean the Lucene document for tags and separators
         * @brief This method strips the tags from document and
         * @param $results All results that came from lucene search
         * @param $id ID of the document to extract from results
         * @author cristiroma
         */
        function getHighlightedContent($results, $id) {
            foreach($results as $result) {
                if( $result->id == $id ) {
                    $str = strip_tags($result->content);
                    //echo $str . "<br /><br />";
                    $str = '...' . str_replace("#TERM#", "<strong>", $str);
                    $str = str_replace("#/TERM#", "</strong>", $str);
                    $str = str_replace("#BREAK#", "<br />...", $str);
                    return $str;
                }
            }
        }



        /**
         * @brief Retrieve from Lucene the documents with highlighted terms Google style.
         * @author cristiroma
         */
        function getDocumentsGoogleStyle($searchUrl, $params, $service_prefix = null) {

		if( !isset($service_prefix) ){
			$service_prefix = $this->getDefaultServicePrefix();
		}

            $oModelDocument = &getModel('document');

            $params->serviceName = $service_prefix.'_document';
            $params->query = '('.$params->query.')'.$params->subquery;
            $params->displayFields = array("id", "title", "content");
            $params->highFields = array( array( "content", "100", "2", "#BREAK#" ), array( "title", "100", "1", "" ) );
            $params->stylePrefix = "#TERM#";
            $params->styleSuffix = "#/TERM#";

            $encodedParams = json_encode($params);
            //$searchResult format: { "results" : [ { "content" : "...", "id" : "302", "title" : "Programming..." }, ... ] }
            $searchResult = FileHandler::getRemoteResource($searchUrl."searchWithHighLightSummaryByMap", $encodedParams, 3, "POST", "application/json; charset=UTF-8", array(), array());

            // 결과가 유효한지 확인
	    // Results confirm the validity of
            if ( !$searchResult && $searchResult != "null") {
                $idList = array();
            } else {
                $idList = $this->result2idArray($searchResult);
            }

            // 결과가 1개 이상이어야 글 본문을 요청함.
	    // Results must be at least one body has requested post.
            $documents = array();
            $highlight = array();
            //$searchResult = $this->getService()->decode($searchResult);
            $searchResult = json_decode($searchResult);
            if (count($idList) > 0) {
                $tmpDocuments = $oModelDocument->getDocuments($idList, false, false);
                // 받아온 문서 목록을 루씬에서 반환한 순서대로 재배열
		// Russineseo received a list of documents returned by rearranging the order
                foreach($idList as $id) {
                    $documents['doc'.$id] = $tmpDocuments[$id];
                    $content = $this->getHighlightedContent( $searchResult->results, $id);
                    $highlight[$id] = $content;
                }
            }

            $page_navigation = new PageHandler($searchResult->totalSize, ceil( (float)$searchResult->totalSize / 10.0 ), $params->curPage, 10);

            $output->total_count = $searchResult->totalSize;
            $output->data = $documents;
            $output->highlight = $highlight;
            $output->page_navigation = $page_navigation;
            return $output;
        }



        /**
         * @brief 글의 id 목록으로 글을 가져옴.
	           Post id list, bringing the article.
         */
        function getDocuments($searchUrl, $params, $service_prefix = null) {
		if( !isset($service_prefix) ){
			$service_prefix = $this->getDefaultServicePrefix();
		}
            $oModelDocument = &getModel('document');

            $params->serviceName = $service_prefix.'_document';
            $params->query = '('.$params->query.')'.$params->subquery;
            $params->displayFields = array("id");

            $encodedParams = $this->getService()->encode($params);
	    	//debug_syslog(1, "luceneModel.getDocuments() encodedParams:".print_r($encodedParams, true)."\n");
            $searchResult = FileHandler::getRemoteResource($searchUrl."searchByMap", $encodedParams, 3, "POST", "application/json; charset=UTF-8", array(), array());

            // 결과가 유효한지 확인
	    // Results confirm the validity of
            if (!$searchResult && $searchResult != "null") {
                $idList = array();
            } else {
                $idList = $this->result2idArray($searchResult);
            }

            // 결과가 1개 이상이어야 글 본문을 요청함.
	    // Results must be at least one body has requested post.
            $documents = array();
            if (count($idList) > 0) {
                $tmpDocuments = $oModelDocument->getDocuments($idList, false, false);
                // 받아온 문서 목록을 루씬에서 반환한 순서대로 재배열
		// Russineseo received a list of documents returned by rearranging the order
                foreach($idList as $id) {
                    $documents['doc'.$id] = $tmpDocuments[$id];
                }
            }
	    	//debug_syslog(1, "searchResult=".$searchResult."\n");
            //$searchResult = $this->json_service->decode($searchResult);
            $searchResult = json_decode($searchResult);
            $page_navigation = new PageHandler($searchResult->totalSize, ceil( (float)$searchResult->totalSize / 10.0 ), $params->curPage, 10);

            $output->total_count = $searchResult->totalSize;
            $output->data = $documents;
            $output->page_navigation = $page_navigation;
            return $output;
        }

	function getDefaultServicePrefix(){
            $oModuleModel = &getModel('module');
            $config = $oModuleModel->getModuleConfig('lucene');
	    return $config->service_name_prefix;
	}

	function getDefaultSearchUrl($searchAPI)
	{
            $oModuleModel = &getModel('module');
            $config = $oModuleModel->getModuleConfig('lucene');
	    syslog(1, "lucene config: ".print_r($config, true)."\n");
	    return $searchUrl = $config->searchUrl.$searchAPI;
	}

	function getISConfig(){
		$oModuleModel = &getModel('module');
		$ISconfig = $oModuleModel->getModuleConfig('integration_search');
		return $ISconfig;

	}

        /**
         * Prepares the documents tree for display
         * by describing the relationship of each node
         * with the page being viewed: parent, sibling, child etc.
         *
         * Used for displaying the sidebar tree menu of the documentation
         */
        function getMenuTree($module_srl, $document_srl, $mid){
                /** Create menu tree */
                $documents_tree = $this->readXedocsTreeCache($module_srl);
                $current_node = &$documents_tree[$document_srl];

                /* Mark current node as type 'active' */
                if($current_node->parent_srl != 0)
                    $current_node->type = 'current';

                /* Find and mark parents */
                $node_srl_iterator = $current_node->parent_srl;
                while($node_srl_iterator > 0){
                    if($documents_tree[$node_srl_iterator]->parent_srl != 0)
                        $documents_tree[$node_srl_iterator]->type = 'parent';
                    else
                        $documents_tree[$node_srl_iterator]->type = 'active_root';
                    $node_srl_iterator =  $documents_tree[$node_srl_iterator]->parent_srl;
                }

                foreach($documents_tree as $node){
                    $node->href = getSiteUrl('document','','mid',$mid,'entry',$node->alias);
                    if(!isset($documents_tree[$node->document_srl]->type)){
                        if($node->parent_srl == 0)
                                $documents_tree[$node->document_srl]->type = 'root';
                        else if($node->parent_srl == $current_node->parent_srl)
                                $documents_tree[$node->document_srl]->type = 'sibling';
                        else if($node->parent_srl == $current_node->document_srl)
                                $documents_tree[$node->document_srl]->type = 'child';
                        else unset($documents_tree[$node->document_srl]);
                    }
                }

                return $documents_tree;
        }
}
?>
