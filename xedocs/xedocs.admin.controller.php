<?php

    /**
     * @class  xedocsAdminController
     *
     * Contains backend views' action methods
     **/

    class xedocsAdminController extends xedocs {

        /**
         * @brief Action method setup - executed before every proc
         */
        function init() {
            $this->setTemplatePath($this->module_path.'tpl');
        }

        /**
         * @brief Adds a new manual
         */
        function procXedocsAdminInsertManual(){
            $oModuleController = &getController('module');
            $oModuleModel = &getModel('module');

            $toc_location = Context::get('toc_location');
            if(!isset($toc_location)){
                Context::set('toc_location', "Left"); //set default
            }

            $args = Context::getRequestVars();
            $args->module = 'xedocs';
            $args->mid = $args->manual_name;
            if($args->use_comment!='N'){
                    $args->use_comment = 'Y';
            }

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
        }

        /**
         * @brief Recreates document aliases
         */
        function procXedocsAdminArrangeList() {
            $oModuleModel = &getModel('module');
            $oDocumentController = &getController('document');

            $module_srl = Context::get('module_srl');
            $module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
            if(!$module_info->module_srl || $module_info->module != 'xedocs') return new Object(-1,'msg_invalid_request');

            $args->module_srl = $module_srl;
            $output = executeQueryArray('xedocs.getDocumentWithoutAlias', $args);
            if(!$output->toBool() || !$output->data) return new Object();

            foreach($output->data as $key => $val) {
                    if($val->alias_srl) continue;
                    $result = $oDocumentController->insertAlias($module_srl, $val->document_srl, $val->alias_title);
                    if(!$result->toBool()) $oDocumentController->insertAlias($module_srl, $val->document_srl, $val->alias_title.'_'.$val->document_srl);
            }
        }

        /**
         * @brief Deletes a manual
         */
        function procXedocsAdminDelete()
        {
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


        function _update_keyword($keyword, $orig_keyword=null)
        {

                //debug_syslog(1, "_update_keyword(".$keyword.", orig=".$orig_keyword.")\n");
                $module_srl = Context::get('module_srl');
                $target_document_srl = Context::get('target_document_srl');

                $oXedocsModel = &getModel('xedocs');
                $updated = $oXedocsModel->update_keyword($module_srl, $orig_keyword, $keyword, $target_document_srl);
                if($updated){
                        //debug_syslog(1, "keyword updated\n");
                }

        }

        function procXedocsAdminEditKeyword()
        {
            $keyword_key = Context::get('orig_title');
            $user_keyword = Context::get('title');
            $target_document_srl = Context::get('target_document_srl');

            $keywords = unserialize($this->module_info->keywords);
            unset($keywords[$keyword_key]);
            $keywords[$user_keyword] = new Object;
            $keywords[$user_keyword]->title = $user_keyword;
            $keywords[$user_keyword]->target_document_srl = $target_document_srl;

            $args->module_srl = $this->module_info->module_srl;
            $args->keywords = serialize($keywords);

            $oModuleController = &getController('module');
            $oModuleController->updateModule($args);

            $this->setMessage("success");
        }

        /**
         * Adds a new keyword
         */
        function procXedocsAdminAddKeyword()
        {
            $user_keyword = Context::get('title');
            $target_document_srl = Context::get('target_document_srl');

            $keywords = array();
            $keywords[$user_keyword] = new stdClass;
            $keywords[$user_keyword]->title = $user_keyword;
            $keywords[$user_keyword]->target_document_srl = $target_document_srl;

            $args = clone($this->module_info);
            $args->keywords = serialize($keywords);

            $oModuleController = &getController('module');
            $output = $oModuleController->updateModule($args);

            if(!$output->toBool()) return $output;

            $this->setMessage("success");
        }
    }
?>