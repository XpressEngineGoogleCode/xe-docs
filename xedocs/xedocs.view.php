<?php
/**
 * @class  xedocsView
 *
 * Contains frontend views' action methods
 **/

    class xedocsView extends xedocs {

            var $search_option = array('title','content','title_content','comment','user_name','nick_name','user_id','tag');

            /**
             * @brief Setup action context
             */
            function init() {
                /* Init template path */
                $template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
                if(!is_dir($template_path) || !$this->module_info->skin) {
                        $this->module_info->skin = 'xe_xedocs';
                        $template_path = sprintf("%sskins/%s/",$this->module_path, $this->module_info->skin);
                }
                $this->setTemplatePath($template_path);

                /* Load document module configuration - see if document version history is enabled */
                $oModuleModel = &getModel('module');
                $document_config = $oModuleModel->getModulePartConfig('document', $this->module_info->module_srl);

                if(!isset($document_config->use_history)){
                        $document_config->use_history = 'N';
                }

                $this->use_history = $document_config->use_history;
                Context::set('use_history', $document_config->use_history);

                /* Load js files needed for all views */
                Context::addJsFile($this->module_path.'tpl/js/manual.js');

                /* Make grant information available in template files  */
                Context::set('grant', $this->grant);
            }

            /**
             * Default module action
             * Shows manual homepage if no document is specified.
             * Otherwise, it displays document content
             *
             * $entry - Represents document alias - the value supplied in the URL
             * $document_srl - Current document srl. It is either retrieved from the
             *                  request URL or identified based on document alias
             */
            function dispXedocsIndex()
            {
                $oXedocsModel = &getModel('xedocs');

                /* Retrieve current document_srl */
                $document_srl = Context::get('document_srl');
                $entry = Context::get('entry');

                if (!isset($document_srl))
                {
                    // If document_srl is not set, get document by alias (entry)
                    $oDocumentModel = getModel('document');
                    $document_srl = $oDocumentModel->getDocumentSrlByAlias($this->module_info->mid, $entry);
                    // If no document was found, just retrieve the root
                    if(!$document_srl)
                        $document_srl = $oXedocsModel->get_first_node_srl($this->module_srl);
                }else{
                    // Check if given document_srl exists (is valid)
                    if(!$oXedocsModel->check_document_srl($document_srl, $this->module_info))
                    {
                        // Mark this view as invalid if the document_srl is wrong
                        unset($document_srl);
                        // Get document_srl of root document
                    }
                }

                $oDocumentModel = &getModel("document");
                if($document_srl){
                    $this->setTemplateFile('document_view');

                    $oDocument = $oDocumentModel->getDocument($document_srl);
                    // TODO Check that visit log is properly setup and works
                    $this->_addToVisitLog($entry);

                    // If current document exists and has keywords, replace keywords with links to corresponding articles
                    if(isset($this->module_info->keywords)){
                        $keywords = $oXedocsModel->string_to_keyword_list($this->module_info->keywords);
                        $kcontent = $oXedocsModel->get_document_content_with_keywords($oDocument, $keywords);
                        if( 0 < $kcontent->fcount ){
                                $content = $kcontent->content;
                        }
                    }
                    else{
                        // Get content without popup menu
                        $content = $oDocument->getContent(false);
                    }
                    $oDocument->add('content', $content);

                } else {
                    $this->setTemplateFile('create_document');

                    $oDocument = $oDocumentModel->getDocument(0);
                    $oDocument->add('title', 'Create new page');
                }

                Context::set('oDocument', $oDocument);


                /* Get manual tree */
                $module_srl=$oDocument->get('module_srl');

                if($document_srl){
                    $documents_tree = $oXedocsModel->getMenuTree($module_srl, $document_srl, $this->module_info->mid);
                    //var_dump($documents_tree);
                }
                Context::set("documents_tree", $documents_tree);

                /* Get versioning information */
                $manual_set = $this->module_info->help_name;
                if($entry)
                    $alias = $entry;
                else
                    $alias = $oDocument->getDocumentAlias();
                $versions = $oXedocsModel->getVersions($manual_set, $alias);

                $version_count = count($versions);
                for($i = 0; $i < $version_count; $i++){
                    $versions[$i]->is_current_version = ($versions[$i]->document_srl == $document_srl);
                    $versions[$i]->href = getSiteUrl('document','','mid',$versions[$i]->mid,'entry',$versions[$i]->alias);
                }
                Context::set("versions",  $versions);

                $meta = $oXedocsModel->get_meta($module_srl, $document_srl);
                Context::set("meta", $meta);

                Context::setBrowserTitle($this->module_info->browser_title." - ".$oDocument->getTitle());

                /* Load navigation data */
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

                /* Add XML filter for comment */
                Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');
            }

            /**
             * @brief View used for editing an existing document or for creating a new one
             */
            function dispXedocsEditPage()
            {
                if(!$this->grant->is_admin){
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
             * @brief View used for displaying document history - a log of all edits made on a document
             */
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
                    $this->setTemplateFile('document_history');
            }

            /**
             * @brief View dor displaying search results
             */
            function dispXedocsSearchResults()
            {
                    $oXedocsModel = &getModel('xedocs');
                    $oDocumentModel = &getModel('document');
                    $oModuleModel = &getModel('module');

                    $moduleList = $oXedocsModel->getModuleList(true);
                    $moduleList = $this->_sortArrayByKeyDesc($moduleList, 'search_rank');
                    Context::set('module_list', $moduleList);

                    $target_mid = $this->module_info->module_srl;
                    $is_keyword = Context::get("search_keyword");

                    $this->_searchKeyword($target_mid, $is_keyword);

                    $this->setTemplateFile('document_search');
            }

            /**
             * @brief View for displaying a list of all the documents
             * from a manual
             */
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

                    $this->setTemplateFile('document_list_all');
            }

            /**
             * @brief Opens view for changing documents hierarchy (tree)
             */
            function dispXedocsModifyTree()
            {
                    if(!$this->grant->is_admin) {

                            return new Object(-1,'msg_not_permitted');
                    }

                    Context::set('isManageGranted', $this->grant->is_admin?'true':'false');
                    $this->setTemplateFile('modify_tree');
            }

            /**
             * @brief Opens view for reply-ing to an existing comment
             */
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

            /**
             * Opens view for editing existing comment
             */
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

            /**
             * @brief Opens view for confirming deletion of a comment
             */
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

            /**
             * @brief Displaying message
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

            /**
             * @brief Sorts array descending by key
             * // TODO See if can be removed and replaced with a query
             */
            function _sortArrayByKeyDesc($object_array, $key ){
                    $key_array = array();
                    foreach($object_array as $obj ){
                            $key_array[$obj->{$key}] = $obj;
                    }

                    krsort($key_array);

                    $result = array();
                    foreach($key_array as $rank => $obj ){
                            $result[] = $obj;
                    }
                    return $result;
            }

            /**
             * @brief Adds info to document - user friendly url and others
             * for pretty displaying in search results
             * // TODO See if it can be replaced / removed
             */
            function _resolveDocumentDetails($oModuleModel, $oDocumentModel, $doc){

                    $entry = $oDocumentModel->getAlias($doc->document_srl);

                    $module_info = $oModuleModel->getModuleInfoByDocumentSrl($doc->document_srl);
                    $doc->browser_title = $module_info->browser_title;
                    $doc->mid = $module_info->mid;


                    if ( isset($entry) ){
                            $doc->entry = $entry;
                    }else{
                            $doc->entry = "bugbug";
                    }
            }

            /**
             * @brief Helper method for search
             */
            function _searchKeyword($target_mid, $is_keyword){
                    $page =  Context::get('page');
                    if (!isset($page)) $page = 1;

                    $search_target = Context::get('search_target');
                    if(isset($search_target)){
                            if ($search_target == 'tag') $search_target = 'tags';
                    }
                    $oXedocsModel = &getModel('xedocs');
                    $oModuleModel = &getModel('module');
                    $oDocumentModel = &getModel('document');


                    $output = $oXedocsModel->search($is_keyword, $target_mid, $search_target, $page, 10);

                    if($output->data)
                    foreach($output->data as $doc){
                            $this->_resolveDocumentDetails($oModuleModel, $oDocumentModel, $doc);
                    }

                    Context::set('document_list', $output->data);
                    Context::set('total_count', $output->total_count);
                    Context::set('total_page', $output->total_page);

                    Context::set('page', $page);
                    Context::set('page_navigation', $output->page_navigation);

                    return $output;
            }

            /**
             * @brief Adds current user visit to document visit log
             */
            function _addToVisitLog($entry)
            {
                    $module_srl = $this->module_info->module_srl;
                    $visit_log = &$_SESSION['xedocs_visit_log'];
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


    }
    ?>
