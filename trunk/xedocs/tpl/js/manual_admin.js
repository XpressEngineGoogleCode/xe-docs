/**
 * @file   modules/xedocs/js/manual_admin.js
 **/


function completeInsertManual(ret_obj) {
    var error = ret_obj['error'];
    var message = ret_obj['message'];

    var page = ret_obj['page'];
    var module_srl = ret_obj['module_srl'];

    alert(ret_obj['message']);

    var url = current_url.setQuery('act','dispXedocsAdminInsertManual');
    if(module_srl) url = url.setQuery('module_srl',module_srl);
    if(page) url.setQuery('page',page);
    location.href = url;
}



function completeDeleteManual(ret_obj) {

    var error = ret_obj['error'];
    var message = ret_obj['message'];
    var page = ret_obj['page'];
    alert(message);

    var url = current_url.setQuery('act','dispXedocsAdminView').setQuery('module_srl','');
    if(page) url = url.setQuery('page',page);
    location.href = url;
}

function completeEditKeyword(ret_obj){
    var error = ret_obj['error'];
    var message = ret_obj['message'];
    var page = ret_obj['page'];
    alert(message);

    var url = current_url.setQuery('act','dispXedocsAdminKeywordList')
    if(page) url = url.setQuery('page',page);
    location.href = url;

}



function completeAddKeyword(ret_obj){
    var error = ret_obj['error'];
    var message = ret_obj['message'];
    var page = ret_obj['page'];
    alert(message);

    var url = current_url.setQuery('act','dispXedocsAdminKeywordList');
    if(page) url = url.setQuery('page',page);

    location.href = url;

}

function completeClearKeywords(ret_obj){
    var error = ret_obj['error'];
    var message = ret_obj['message'];
    alert(message);

    var url = current_url.setQuery('act','dispXedocsAdminKeywordList');

    location.href = url;
}

function completeDeleteKeyword(ret_obj){
    var error = ret_obj['error'];
    var message = ret_obj['message'];
    alert(message);

    var url = current_url.setQuery('act','dispXedocsAdminKeywordList');

    location.href = url;
}

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

