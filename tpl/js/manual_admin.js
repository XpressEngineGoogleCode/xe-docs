/**
 * @file   modules/xedocs/js/manual_admin.js
 **/

function doCartSetup(url) {
    var module_srl = new Array();
    jQuery('#fo_list input[name=cart]:checked').each(function() {
        module_srl[module_srl.length] = jQuery(this).val();
    });

    if(module_srl.length<1) return;

    url += "&module_srls="+module_srl.join(',');
    popopen(url,'modulesSetup');
}

function doArrangeXedocsList(module_srl) {
    exec_xml('xedocs','procXedocsAdminArrangeList',{module_srl:module_srl},function() {location.reload();});
}



function insertManualPage(id, document_srl, mid, browser_title)
{


    if(!window.opener){
    	alert('!window.opener');
    	window.close();
    }

    if(typeof(opener.insertSelectedManualPage)=='undefined'){
    	alert('undefined opener.insertSelectedManualPage');
    	return;
    }

    opener.insertSelectedManualPage(id, document_srl, mid, browser_title);
    window.close();

}

