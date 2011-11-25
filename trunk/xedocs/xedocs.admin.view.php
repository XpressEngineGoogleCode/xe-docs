<?php
/**
 * @class xedocsAdminView
 *
 * Contains backend views' action methods
 */

    class xedocsAdminView extends xedocs {

        /**
         * @brief View setup - method called before each view method
         */
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

        /**
         * @brief Method for displaying a list of all xedocs manuals
         */
        function dispXedocsAdminView() {
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

            $this->setTemplateFile('index');
        }

        /**
         * @brief Displays config info for a manual
         */
        function dispXedocsAdminManualInfo()
        {
            $this->dispXedocsAdminInsertManual();
        }

        /**
         * @brief Displays manual permissions
         */
        function dispXedocsAdminGrantInfo()
        {
            $oModuleAdminModel = &getAdminModel('module');
            $grant_content = $oModuleAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
            Context::set('grant_content', $grant_content);

            $this->setTemplateFile('grant_list');
        }

        /**
         * @brief Displays xedocs aditional setup
         */
        function dispXedocsAdminAdditionSetup() {
            $content = '';

            $output = ModuleHandler::triggerCall('module.dispAdditionSetup', 'before', $content);
            $output = ModuleHandler::triggerCall('module.dispAdditionSetup', 'after', $content);
            Context::set('setup_content', $content);

            $this->setTemplateFile('addition_setup');
        }

        /**
         * @brief Displays manual skin config
         */
        function dispXedocsAdminSkinInfo()
        {
            // Call the common page for managing skin information
            $oModuleAdminModel = &getAdminModel('module');
            $skin_content = $oModuleAdminModel->getModuleSkinHTML($this->module_info->module_srl);
            Context::set('skin_content', $skin_content);

            $this->setTemplateFile('skin_info');
        }

        /**
         * @brief Displays page for recreating document aliases
         */
        function dispXedocsAdminArrange() {

                $this->setTemplateFile('arrange_list');
        }

        /**
         * @brief Displays manual info if a manual is selected or a page for creating a new one
         */
        function dispXedocsAdminInsertManual()
        {
            if(!in_array($this->module_info->module, array('admin', 'xedocs'))) {
                    return $this->alertMessage('msg_invalid_request');
            }

            $oModuleModel = &getModel('module');
            $skin_list = $oModuleModel->getSkins($this->module_path);
            Context::set('skin_list',$skin_list);

            $oLayoutModel = &getModel('layout');
            $layout_list = $oLayoutModel->getLayoutList();
            Context::set('layout_list', $layout_list);

            $this->setTemplateFile('manual_insert');
        }

        /**
         * @brief Displays page for editing keywords
         */
        function dispXedocsAdminEditKeyword()
        {
            $has_target_page = false;
            $target_document_srl = Context::get('target_document_srl');

            if( isset($target_document_srl) ){
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
            $this->setTemplateFile("edit_keyword");
        }

        /**
         * @brief Displays page for adding a keyword
         */
        function dispXedocsAdminAddKeyword()
        {
            $this->dispXedocsAdminEditKeyword();
        }

        /**
         * Displays a list of documents which contain a given keyword,
         * in order to select one from the list.
         *
         * Used for keywords, to choose which page a keyword should link to.
         */
         function dispXedocsAdminManualPageSelect()
        {
            if(!Context::get('is_logged')) return new Object(-1, 'msg_not_permitted');

            $module_srl = Context::get('module_srl');
            $search_keyword = Context::get('search_keyword');

            $page =  Context::get('page');
            if (!isset($page)) $page = 1;

            $search_target = Context::get('search_target');
            if(!isset($search_target)) $search_target = 'title';
            if ($search_target == 'tag') $search_target = 'tags';

            $oXedocsModel = &getModel('xedocs');
            $oModuleModel = &getModel('module');
            $oDocumentModel = &getModel('document');

            $output = $oXedocsModel->search($search_keyword, $module_srl, $search_target, $page, 10);

            $module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);

            if($output->data)
                foreach($output->data as $doc){
                    $entry = $oDocumentModel->getAlias($doc->document_srl);
                    $doc->browser_title = $module_info->browser_title;
                    $doc->mid = $module_info->mid;

                    if (isset($entry) ){
                            $doc->entry = $entry;
                    }else{
                            $doc->entry = "bugbug";
                    }
                }

            Context::set('document_list', $output->data);
            Context::set('total_count', $output->total_count);
            Context::set('total_page', $output->total_page);

            Context::set('page', $page);
            Context::set('page_navigation', $output->page_navigation);

            $this->setTemplateFile('document_selector');
        }

        function dispXedocsAdminKeywordList()
        {
            $oDocumentModel = &getModel('document');

            $page = Context::get('page');

            $oXedocsModel = &getModel('xedocs');
            if(isset($this->module_info->keywords)){
                $filter_keyword = Context::get('filter_keyword');
                $items_per_page = 10;
                if(!isset($page)){
                        $page = 1;
                }

                $keywords = unserialize($this->module_info->keywords);

                $paged_keywords = array();

                $start = $items_per_page * ($page-1);
                $keyword_count = 0;

                foreach($keywords as $keyword){
                    if(isset($filter_keyword)){
                        $pos = strpos($keyword->title, $filter_keyword);
                        if($pos === false) continue;
                    }
                    $keyword_count++;
                    if($keyword_count < $start) continue;
                    if($keyword_count > $start + $items_per_page) break;

                    $paged_keywords[] = $keyword;
                }

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
            }

            $this->setTemplateFile("keyword_list");
        }

        /**
         * @brief Displays delete confirmation page
         */
        function dispXedocsAdminDeleteManual()
        {
            if(!Context::get('module_srl')){
                    return $this->dispXedocsAdminView();
            }

            if(!in_array($this->module_info->module, array('admin', 'xedocs'))) {
                    return $this->alertMessage('msg_invalid_request');
            }

            $oDocumentModel = &getModel('document');
            $document_count = $oDocumentModel->getDocumentCount($this->module_info->module_srl);
            $this->module_info->document_count = $document_count;

            $this->setTemplateFile('manual_delete');
        }

    }


    ?>