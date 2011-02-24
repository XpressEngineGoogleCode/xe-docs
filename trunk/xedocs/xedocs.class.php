<?php


function debug_syslog($level, $msg)
{
	syslog($level, $msg);
}

function get_resource_contents($stream)
{

	//return stream_get_contents($stream);



	$contents = '';

	$fp = $stream ;

	if(!$fp){
		//return stream_get_contents($stream);

		exit("get_resource_contents failed\n");
	}


	while (!feof($fp)) {

		$contents .= fread($fp, 2);

	}

	fclose($fp);
	return $contents;


}


class HelpNode
{
	public $name = '';
	public $relpath = '';
	public $parent;
	public $children =  array();
	public $tags = array();
	public $relname="";
	public $manual;
	public $level =0;
	public $document_srl;

	function get_indent(){

		$indent ='';
		for($i=0; $i<$this->level; $i++){
			$indent=$indent.'&nbsp&nbsp&nbsp ';
		}
		return $indent;
	}

	function getLinkedUrl($base){
		if( ! $this->hasUrl() ){ return $this->name; }
		return "<a href='".$base.$this->relpath."'>".$this->name."</a>";
	}

	function hasUrl(){
		return isset($this->relpath) && 0 < strlen($this->relpath);
	}

	function geturl(){
		return "http://www.cubrid.org/webmanual/2.1/".$this->relpath;
	}

	function get_named_child($name){
		foreach($this->children as $i => $val ){
			if ( 0 == strcmp($name, $val->name) ){
				return $val;
			}
		}
		return false;
	}

	function add_child($child){
		$this->children[] = $child;
		$child->parent = $this;
	}

}

class DocumetationManual
{
	public $name = '';
	public $version = '';
	public $meta = '';
	public $url ="";
	public $root = array(); //array of help nodes
}




class TocTreeBuilder{

	function  init(){
	}

	function do_print($x){
		print($x);
	}

	var $archive;
	var $archive_filename;

	function download_archive($fname){
		if( isset($this->archive)) return true;

		$zipPath = tempnam(".", "manual");

		$this->archive_filename = $zipPath;

		$data = file_get_contents($fname);
		file_put_contents($zipPath, $data);

		$z = new ZipArchive();
		if ( ! $z->open($zipPath) ) {
			$this->doprint("cannot open archive".$fname."\n");
			return false;
		}

		$this->archive = $z;
		return true;
	}

	function remove_archive()
	{
		try{

			if( isset($this->archive)) {

				$this->archive->close();
				unset($this->archive);

			}
			if( isset($this->archive_filename )) {

				unlink($this->archive_filename);
				unset($this->archive_filename);

			}
		}catch(Exception $w){
			debug_syslog(1, $w->getMessage());
		}
	}

	function getContent( $toc_node )
	{
		$relpath = $toc_node->relpath;
		if( !isset($this->archive) ){
			debug_syslog(1, "archive not set\n");
			return false;
		}
		if( 0 != strcmp('', $relpath) ){
			return $this->get_file_content($relpath);
				
		}

		return $this->get_child_list_content($toc_node);


	}



	function get_child_list_content($toc_node){

		$content = "<h2>".$toc_node->name." </h2><ul>";

		foreach($toc_node->children as $i => $child){
			$content .= "<!-- child->relpath=".$child->relpath." name=".$child->name."-->\n";
			$content .= "<li><a href=\"".$child->relpath."\" >". htmlspecialchars($child->name)." </a></li>";
		}

		$content .= "</ul>";

		return $content;
	}


	function getFile($fname, $name)
	{

		if(!$this->download_archive($fname)){
			return false;
		}

		return $this->get_file_content($name);

	}

	function get_file_content($name)
	{
		return get_resource_contents($this->archive->getStream($name));
	}

	function createChildTreeNode($fname, $item){

		if(!$item->hasAttributes()){
			//echo"no attributes \n";
			return;
		}
		$node = new HelpNode();
		$name = $item->getAttribute('name');
		//echo "name = ".$name."\n";
		$url = $item->getAttribute('url');
		//echo "url = ".$url."\n";
		$ref = $item->getAttribute('ref');

		$node->name = $name;
		$node->relpath = $url;
		if( "book" == $item->nodeName ){
			//adding children
			$this->addChildren($fname,$item, $node);
		}
		//here we have to open file at attr 'ref' and load the tree
		if("chunk" == $item->nodeName ) {
			//echo "ref = ".$ref."\n";
			//$node->ref = $ref;
			//adding children
			$subtree = $this->getSubTree($fname, $ref);
			return $subtree;
		}
		return $node;
	}

	function getSubTree($fname, $ref){
		$ref = "whxdata/".$ref;
		$xml = $this->getFile($fname, $ref);
		$tree = $this->getTreeFromXml($fname, $xml);

		return $tree;
	}


	function addChildren($fname, $child, $parent){
		foreach ($child->childNodes AS $item)
		{
			$node = $this->createChildTreeNode($fname, $item);

			if(!isset($node) || $node->name == "") continue;

			if( $node->name == "home"){
				foreach($node->children as $cn){
					$parent->add_child($cn);
				}
			}else{
				$parent->add_child($node);
			}

		}
	}

	function getTreeFromXml($fname, $xml){

		$tree = new HelpNode();

		$xmlDoc = new DOMDocument();
		$i = $xmlDoc->loadXML($xml);

		$tree->name ="home";

		$x = $xmlDoc->firstChild;

		if("tocdata"== $x->nodeName){

			//echo "add children\n";
			$this->addChildren($fname, $x, $tree);
		}

		return $tree;
	}

	function getTree($filename, $orphan_html)
	{
		debug_syslog(1, "TocTreeBuilder get tree\n");
		$xml_name = "whxdata/whtdata0.xml";
		$f = $this->getFile($filename, $xml_name);

		//debug_syslog(1, "TocTreeBuilder base content: ".$f."\n");

		$toc = $this->getTreeFromXml($filename, $f);

		return $toc;
	}

}



class TocWalkerSimple{

	public $level = 0;

	function walk($toc_home, $processor)
	{
		$processor->process_node($toc_home, $this->level);
		$this->level++;
		foreach($toc_home->children as $key => $value){
			$this->walk($value, $processor);
		}
		$this->level--;
	}

}

class TocWalker{



	function walk($toc_home, $processor)
	{
		$sw = new TocWalkerSimple();

		$processor->start();
		$sw->walk($toc_home, $processor);
		$processor->end();
	}

}




class TocProcessor{

	function process_node($toc_node, $level){
	}

	function start(){}
	function end(){}
}



class TocPrintProcessor extends TocProcessor {


	function do_print($line){
		print($line);
	}

	function print_indent($level){
		for($i=0; $i<$level; $i++){
			$this->do_print(' ');
		}
	}

	function process_node($toc_node, $level){
		$this->print_indent($level);
		$this->do_print($toc_node->name ." ->".$toc_node->relpath."\n"  );
	}

	function start(){$this->do_print("---- start -----\n");}
	function end(){$this->do_print("---- end -----\n");}

}

class SyslogTocProcessor extends TocProcessor{

	function do_print($line){
		debug_syslog(1,$line);
	}
}

class ArrayTocProcessor extends TocProcessor{

	public $nodes=array();

	function process_node($toc_node, $level)
	{

		$toc_node->level = $level;
		$this->nodes[] = $toc_node;
	}

	function get_indent($level){
		$indent ='';
		for($i=0; $i<$level; $i++){
			$indent=$indent.'&nbsp&nbsp&nbsp ';
		}
		return $indent;
	}

	function start(){}
	function end(){}
}




class xedocs extends ModuleObject {

	var $manual_ids = array();

	function moduleInstall() {
		return new Object();
	}

	/**
	 * @return true if module needs update, false otherwise
	 */
	function checkUpdate() {
		return false;

		$oModuleModel = &getModel('module');

		if($oModuleModel->getActionForward('xedocs_index', 'view', 'xedocs')){
			//we already have action so report no update needed
			return false;
		}
		return true;
	}

	function moduleUpdate() {
		error_log("Updating xedocs module...");
		$oModuleController = &getController('module');
		$oModuleController->insertActionForward('xedocs', 'view', 'xedocs');
		return new Object(0, 'success_updated');
	}

	function recompileCache() {
		FileHandler::removeFilesInDir(_XE_PATH_."files/cache/xedocs");
	}
}


?>
