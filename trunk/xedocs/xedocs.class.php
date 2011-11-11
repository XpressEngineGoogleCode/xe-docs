<?php

    require_once(_XE_PATH_.'modules/xedocs/xedocs.lib.php');
    
    /**
     * Base class for XE Docs module.
     */

    class xedocs extends ModuleObject {

            function moduleInstall() {
                    return new Object();
            }

            /**
             * @return true if module needs update, false otherwise
             */
            function checkUpdate() {
                    return false;
            }

            function moduleUpdate() {
                    return new Object(0, 'success_updated');
            }

            function recompileCache() {
                    FileHandler::removeFilesInDir(_XE_PATH_."files/cache/xedocs");
            }
    }


?>
