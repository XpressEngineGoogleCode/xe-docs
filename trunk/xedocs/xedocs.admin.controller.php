<?php


function startsWith($haystack,$needle,$case=true) {
	if($case){return (strcmp(substr($haystack, 0, strlen($needle)),$needle)===0);}
	return (strcasecmp(substr($haystack, 0, strlen($needle)),$needle)===0);
}

function endsWith($haystack,$needle,$case=true) {
	if($case){return (strcmp(substr($haystack, strlen($haystack) - strlen($needle)),$needle)===0);}
	return (strcasecmp(substr($haystack, strlen($haystack) - strlen($needle)),$needle)===0);
}

function customError($errno, $errstr){

	debug_syslog(1, "Error:  [".$errno."] ".$errstr."\n");
	debug_syslog(1, "Ending Script\n");
	//die();
}


class xedocsAdminController extends xedocs {

	function init() {
		$this->setTemplatePath($this->module_path.'tpl');
	}

	function is_good_archive_url($url){
		return true;
	}

	function procXedocsAdminInsertManual(){

		debug_syslog(1, "procXedocsAdminInsertManual\n");

		$oModuleController = &getController('module');
		$oModuleModel = &getModel('module');
			
		$args = Context::getRequestVars();
		$args->module = 'xedocs';
		$args->mid = $args->manual_name;
		if($args->use_comment!='N'){
			$args->use_comment = 'Y';
		}

		$module_name = $args->manual_name;
		unset($args->manual_name);

		if($args->module_srl) {
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl);
			if($module_info->module_srl != $args->module_srl){
				unset($args->module_srl);
			}
		}

		if(!$args->module_srl) {
			$output = $oModuleController->insertModule($args);
			$msg_code = 'success_registed';
		} else {
			$output = $oModuleController->updateModule($args);
			$msg_code = 'success_updated';
		}

		if(!$output->toBool()) {
			return $output;
		}

		$this->add('page',Context::get('page'));
		$this->add('module_srl',$output->get('module_srl'));
		$this->setMessage($msg_code);

		debug_syslog(1, "Inserted manual module ". $module_name);

	}


	function procXedocsAdminDelete()
	{
		debug_syslog(1, "procXedocsAdminDelete\n");

		$module_srl = Context::get('module_srl');

		$oModuleController = &getController('module');
		$output = $oModuleController->deleteModule($module_srl);

		if(!$output->toBool()){
			return $output;
		}

		$this->add('module','xedocs');
		$this->add('page',Context::get('page'));
		$this->setMessage('success_deleted');
	}



	function procXedocsAdminImportArchive()
	{
		debug_syslog(1, "procXedocsAdminImportArchive - insert module \n");
		// first insert a manual
		$oModuleController = &getController('module');
		$oModuleModel = &getModel('module');
			
		$args = Context::getRequestVars();
		$args->module = 'xedocs';
		$args->mid = $args->manual_name;

		if($args->use_comment!='N') {
			$args->use_comment = 'Y';
		}

		$module_name = $args->manual_name;
		unset($args->manual_name);

		if($args->module_srl) {
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl);
			if($module_info->module_srl != $args->module_srl) {
				unset($args->module_srl);
			}
		}

		if(!$args->module_srl) {
			$output = $oModuleController->insertModule($args);
			$msg_code = 'success_registed';
		} else {
			$output = $oModuleController->updateModule($args);
			$msg_code = 'success_updated';
		}

		if(!$output->toBool()) {
			return $output;
		}

		$this->add('page',Context::get('page'));
		$module_srl = $output->get('module_srl');

		debug_syslog(1, "module_srl=".$module_srl."\n");

		$this->add('module_srl',$module_srl);
		$this->setMessage($msg_code);

		////////////
		debug_syslog(1, "procXedocsAdminImportArchive import archive \n");

		$args = Context::gets('help_archive_url','help_name', 'help_meta', 'help_tags');

		if( !$this->is_good_archive_url($args->help_archive_url) ) {
			debug_syslog(1, "Bad archive srl\n");
			return ;
		}

		debug_syslog(1, "Importing archive: '".$args->help_archive_url."'\n");
		//import archive contents
		$toc = $this->import_help_archive( $args->help_archive_url,  $module_srl );

		debug_syslog(1, "Import complete , toc has ".count($toc->children)." childrens\n");

		$oModuleModel = &getModel('module');

		$config = $oModuleModel->getModuleConfig('xedocs');
		$config = array();

		$man = new DocumetationManual();
		$man->name = $args->help_name;

		foreach( $toc->children as $c => $root){
			$man->root[] = $root;
		}

		$man->url = $args->help_archive_url;

		$config[] =  $man;
		$oModuleController = &getController('module');

		$output = $oModuleController->insertModuleConfig('xedocs',$config);
		debug_syslog(1, "config update complete - inserting content \n");


		return $output;
	}


	function import_help_archive($url, $module_srl)
	{
		debug_syslog(1, "import_help_archive: ".$url."\n");
		$orphan = array();
		$builder = new TocTreeBuilder();
		$builder->init();

		$toc = $builder->getTree($url, $orphan);

		if(!isset($toc) || !$toc){
			debug_syslog(1, "cannot build toc");
			return false;
		}

		$this->build_content($toc, $builder, $module_srl);

		return $toc;

	}

	function build_content($toc, $builder, $module_srl)
	{



		$walker = new TocWalker();
		$processor = new ContentBuilderTocProcessor();
		$processor->set_builder($builder);
		$processor->set_module_srl($module_srl);
		$processor->controller = $this;

		debug_syslog(1, "build_content module_srl=".$module_srl."\n");

		$walker->walk($toc, $processor);
		debug_syslog(1, "first pass walk complete\n");
		//$processor->dump_node_paths();

		$processor->set_second_pass();

		$walker->walk($toc, $processor);
		debug_syslog(1, "second pass walk complete\n");

		$builder->remove_archive();

	}

	function getModel($name)
	{
		$oModule = &getModel($name);
		return $oModule;
	}

	function getController($name)
	{
		$oController =  &getController($name);
		return $oController;
	}

}

include 'simple_html_dom.php';
include 'file/fileController.php';

class ContentBuilderTocProcessor extends TocProcessor
{
	var $builder;
	var $grant;
	var $module_srl;
	var $msg_code;
	var $docid;
	var $node_paths = array();
	var $second_pass=false;
	public $controller;


	function dump_node_paths()
	{
		debug_syslog(1, "Node Paths\n");
		debug_syslog(1, "----------------------------------\n");
		foreach( $this->node_paths as $i => $value ) {
			debug_syslog(1, "[".$i."]->[".$value."]\n");
		}
		debug_syslog(1, "----------------------------------\n");
	}

	function set_second_pass()
	{
		$this->second_pass = true;
		debug_syslog(1, "Total ".$this->docid." documents inserted\n");
		$this->docid=0;
	}

	function set_builder($b)
	{
		$this->builder = $b;
	}

	function set_module_srl($mid)
	{
		$this->module_srl = $mid;
	}


	function process_node($toc_node, $level)
	{

		$this->docid++;

		if( !$this->second_pass ){
			$this->insert_document($toc_node);
		}else{
			$this->resolve_links($toc_node);
		}
	}

	function get_document_link($document_srl)
	{

		if( !isset($document_srl) ) return false;

		return "/xe/?module_srl=".$this->module_srl."&document_srl=".$document_srl."&act=dispXedocsContents";
	}

	function resolve_links($toc_node)
	{



		if( 0 == strcmp('home', $toc_node->name)){
			return;
		}


		set_time_limit(0);

		if( !isset($toc_node->document_srl) ) {
			debug_syslog(1, "invalid $toc_node document_srl \n");
			return;
		}

		debug_syslog(1, "resolve_links for ".$toc_node->name."\n");
		debug_syslog(1, "node_paths  len ".count($this->node_paths)."\n");

		//$content = $this->get_document_content($toc_node->document_srl);
		$content = $this->get_node_contents($toc_node);

		debug_syslog(1, "get_document_content -".$toc_node->name."-document_srl=".$toc_node->document_srl." ok \n");

		if(!$content) {

			debug_syslog(1, "not content\n");
			return;
		}

		$dom = str_get_html($content);

		debug_syslog(1, "dom parsed \n");
		$changed = false;

		foreach( $dom->find('a') as $element ) {

			//if archive link

			if( 0 == strcmp('http' , substr($element->href, 0, 4)) ) {

				debug_syslog(1, "link=".$element->href." is not archive link\n");

				continue;
			}

			$changed = $this->resolve_single_link($toc_node, $element, $changed);

		}


		foreach( $dom->find('img') as $element ) {
			$this->attach_image($toc_node, $element);
		}

		$new_content = "".$dom;

		debug_syslog(1, "updating document_srl=".$toc_node->document_srl." content with new links ...\n");

		$this->update_document_content($toc_node, $new_content);
		debug_syslog(1, "document updated with new links \n");
	}

	function attach_image($toc_node, $element)
	{
			

		$src = $element->src;
		debug_syslog(1, "relpath = ".$toc_node->relpath." src=".$src."\n");

			
		$name = substr($src, strpos($src, "/")+1);
		$extension = substr($src, strpos($src, "."));

		$file_info['name'] = $name;
		debug_syslog(1, "file_info['name'] =".$file_info['name'] ."\n");

		if( startsWith($src, '../')){
			$path = substr( $src, 3 );
		}else{
			$path = dirname($toc_node->relpath).'/'.$src;
		}
		debug_syslog(1, "geting image file from ".$path."\n");
		$image_content = $this->builder->get_file_content($path);

		$tmpfilename = tempnam(sys_get_temp_dir(), 'img').$extension;
		file_put_contents($tmpfilename, $image_content);

		$file_info['tmp_name'] = $tmpfilename;
		debug_syslog(1, "file_info['tmp_name']=".$file_info['tmp_name']."\n");

		$upload_target_srl = $toc_node->document_srl;
		debug_syslog(1, "upload_target_srl=".$upload_target_srl." \n");
		$oFileController = $this->controller->getController("file");

		$output = $oFileController->insertFile($file_info, $this->module_srl, $upload_target_srl, 0, true);

		if(!$output->toBool()) {
			debug_syslog(1, "insert file failed"."\n");

		}else{

			$element->src = $output->get('uploaded_filename');
			$element->alt = $name;
			$element->{'editor_component'} = 'image_link';
		}
		//todo remove temp file
		unlink($tmpfilename);

	}

	function is_single_file_ref($link)
	{
		return false==strpos($link, '/');
	}

	function resolve_single_link($toc_node, $element, $changed)
	{
		//assumes that $element href is a relative archive link
		$link  = $element->href;

		debug_syslog(1, "link=|".$element->href."| resolve toc_node.relpath=[".$toc_node->relpath."]\n");

		if( !isset($element->href)                    //no link
		|| 0 == strcmp('', trim($element->href))  //empty link
		|| startsWith($element->href, "#") )      //name link
		{

			return $changed;
		}


		if( startsWith($element->href, "./")
		|| $this->is_single_file_ref($element->href) ) {

			$orig = $element->href;
			$current_dir = dirname($toc_node->relpath);
			$element->href = $current_dir.'/'.$element->href;
			debug_syslog(1, "resolve_single_link same_dir normalize_link from ".$orig." to ".$element->href."\n");
		}
		else if( startsWith($element->href, "../") ){
			$orig = $element->href;
			$element->href = substr( $element->href, 3 );

			debug_syslog(1, "resolve_single_link normalize_link from [".$orig."] to [".$element->href."] \n");
		}
			
		//handle names in links .
		$idx=strpos($element->href, '#');
		$hasname = false;
		if( false != $idx){
			$file = substr($element->href, 0, $idx);
			$name = substr($element->href, $idx+1);
			$element->href = $file;
			$hasname= true;
		}

//		$idx1 = (int)strpos($element->href, '/');
//		if( false != $idx1){
//			$file = substr($element->href, $idx1+1);
//			$element->href = $file;
//		}

		debug_syslog(1, "resolve_single_link ".$element->href."\n");

		if( isset($this->node_paths[$element->href] ) ) {

			$referred_toc = $this->node_paths[$element->href];
			debug_syslog(1, "  referred_doc=".$referred_toc->name." \n");
			$reffered_srl = $referred_toc->document_srl;

			$new_link = $this->get_document_link($reffered_srl);

			if(!$new_link) return $changed;

			if($hasname){
				$new_link .='#'.$name;
			}

			debug_syslog(1, " replace link for ".$toc_node->name."\n");
			debug_syslog(1, "    from:  ".$element->href."\n");
			debug_syslog(1, "      to:  ".$new_link."\n");

			$element->href = $new_link;
			return true;
		}else{
			debug_syslog(1, "cannot a document_srl for find |".$element->href."|\n");
			return $changed;
		}

	}

	function update_document_content($toc_node, $content)
	{
		if(!isset($toc_node->document_srl)){
			debug_syslog(1, "update_document_content 0 - no document_srl set \n");
			return false;
		}

		$oDocumentModel = $this->controller->getModel('document');

		$oDocument = $oDocumentModel->getDocument($toc_node->document_srl);
		if(!isset($oDocument) || !$oDocument->toBool() ){
			debug_syslog(1, "update_document_content cannot find $oDocument\n");
			return;
		}
		debug_syslog(1, "update_document_content 1 \n");

		$oDocumentController = $this->controller->getController('document');

		debug_syslog(1, "update_document_content 2 \n");

		$obj = NULL;
		$obj->{'module_srl'} = $this->module_srl;
		$obj->{'allow_comment'} = 'Y';
		$obj->{'nick_name'} = 'anonymous';
		$obj->{'title'} = $toc_node->name;
		$obj->{'content'} = $content;
		$obj->{'document_srl'} = $toc_node->document_srl;
		$obj->{'category_srl'} = $oDocument->get('category_srl');

		set_time_limit(0);
		try{
				
			$output = $oDocumentController->updateDocument($oDocument, $obj);
				
		}catch(Exception $w){
			debug_syslog(1, "Exception: ".$w->getMessage()."\n");
		}

		debug_syslog(1, "update_document_content 4 \n");

		if(! $output->toBool() ){
			debug_syslog(1, "failed to update document with new links\n");
		}
	}

	function get_document_content($document_srl)
	{
		$oDocumentModel = &getModel('document');
		$oDocument = $oDocumentModel->getDocument($document_srl);

		if(!$oDocument->isExists()) return false;

		return $oDocument->getContent(false);
	}


	function removeJS($contents)
	{

		$start = "<script";
		$s = strpos($contents, $start);
		$end="</script>";
		$e = stripos($contents, $end, $s);

		if(FALSE === $s || FALSE === $e) {
			return $contents;
		}

		$lenght = $e-$s+strlen($end);
		$sub = substr($contents, $s, $lenght);

		if(false === strpos($sub, "<p>")){
			$contents = str_replace($sub, "",$contents);
			$contents = $this->removeJS($contents);
		}else{
			$contents = $this->removeCDATA($contents);
			$contents = $this->removeJSLine($contents);
			$contents = $this->removeJSTag($contents);
		}

		return $contents;
	}

	function removeJSTag($contents)
	{
		$end="</script>";
		$e = strpos($contents, $end);

		if(FALSE === $e){
			return $contents;
		}

		$contents = str_replace($end, "",$contents);
		$contents = $this->removeJSTag($contents);

		return $contents;
	}

	function removeJSLine($contents){

		$start = "<script";
		$s = strpos($contents, $start);
		$end=">";
		$e = stripos($contents, $end, $s);

		if(FALSE === $s || FALSE === $e){
			return $contents;
		}
		$lenght = $e-$s+strlen($end);
		$sub = substr($contents, $s, $lenght);
		$contents = str_replace($sub, "",$contents);
		$contents = $this->removeJSLine($contents);
		return $contents;
	}

	function removeCDATA($contents)
	{
		$start = "//<![CDATA[";
		$s = strpos($contents, $start);
		$end="//]]>";
		$e = stripos($contents, $end, $s);

		if(FALSE === $s || FALSE === $e){
			return $contents;
		}

		$lenght = $e-$s+strlen($end);
		$sub = substr($contents, $s, $lenght);

		$contents = str_replace($sub, "",$contents);
		$contents = $this->removeCDATA($contents);

		return $contents;
	}




	function get_node_contents($toc_node)
	{
		if(!(strpos($toc_node->relpath, '#') === false)) {

			debug_syslog(1, " Node ".$toc_node->name." has bad relpath : ". $toc_node->relpath ."\n");
			$contents = "<html><body><h1>".$toc_node->name."--- bad relpath ----".$toc_node->relpath."</h1></body></html>";

		}else{

			$contents = $this->builder->getContent($toc_node);

		}

		return $this->removeJS($contents);

	}

	function insert_tree_node($toc_node)
	{

		$args->{'parent_srl'} = $toc_node->parent->document_srl;
		$args->{'target_srl'} = $toc_node->parent->document_srl;
		$args->{'source_srl'} = $toc_node->document_srl;
		$args->module_srl     =  $this->module_srl;
		$args->title 	      = $toc_node->name;

		$output = executeQuery('xedocs.insertTreeNode',$args);
		return $output;

	}


	function insert_document($toc_node)
	{


		if( 0 == strcmp('home', $toc_node->name)) return;

		set_time_limit(0);

		//if relpath contains a # then it is a link to a name into a html
		$idx = -1;
		debug_syslog(1, "insert_document |".$toc_node->name."|\n");

		$oDocumentModel = $this->controller->getModel('document');
		$oDocumentController = $this->controller->getController('document');

		if( false != ($idx = strpos($toc_node->relpath, "#")) ){

			$toc_node->rel_name = substr($toc_node->relpath, 1+$idx);
			$toc_node->relpath = substr($toc_node->relpath, 0, $idx);

			//need to check if html content is already there, then fetch its document_srl
			debug_syslog(1, "fix relpath to ".$toc_node->relpath."\n");
			if ( isset($this->nodepaths[$toc_node->relpath] ) )
			{
				$orig_node = $this->nodepaths[$toc_node->relpath];
				$toc_node->document_srl = $orig_node->document_srl;
				$oDocumentController->insertAlias($this->module_srl, $toc_node->document_srl, $toc_node->name);

				return $this->insert_tree_node($toc_node);
			}
		}




		$obj = NULL;
		$obj->{'module_srl'} = $this->module_srl;
		$obj->{'allow_comment'} = 'Y';
		$obj->{'nick_name'} = 'anonymous';
		$obj->{'title'} = $toc_node->name;


		$contents = $this->get_node_contents($toc_node);

		$obj->{'content'} = $contents;


		settype($obj->title, "string");
		if($obj->title == ''){
			$obj->title = 'Untitled';
		}


		$output = $oDocumentController->insertDocument($obj);

		$obj->{'document_srl'} = $output->get('document_srl');

		$toc_node->document_srl = $obj->document_srl;

		if ( 0 != strcmp('', $toc_node->relpath ) ){
			$this->node_paths[$toc_node->relpath] = $toc_node;
		}

		$oDocumentController->insertAlias($obj->module_srl, $obj->document_srl, $obj->title);

		if(!$output->toBool()){
			debug_syslog(1, "document insert false output\n");
			$msg_code = 'failure_registed';
			return $output;
		}

		$msg_code = 'success_registed';

		//$entry = $oDocumentModel->getAlias($output->get('document_srl'));

		$output = $this->insert_tree_node($toc_node);

	}

	function start()
	{
		$this->docid=0;
	}

	function end()
	{
		debug_syslog(1, "inserting complete !!! \n");
	}

}


?>