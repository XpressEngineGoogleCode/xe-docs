/**
 * @file   modules/xedocs/js/manual.js
 **/


function doDeleteManual(document_srl) {
    var params = new Array();
    params['mid'] = current_mid;
    params['document_srl'] = document_srl;
    exec_xml('xedocs','procXedocsDeleteDocument', params);
}

function completeInsertComment(ret_obj) {
    var error = ret_obj['error'];
    var message = ret_obj['message'];
    var mid = ret_obj['mid'];
    var document_srl = ret_obj['document_srl'];
    var comment_srl = ret_obj['comment_srl'];
    var entry = ret_obj['entry'];

    /*var url = current_url.setQuery('mid',mid).setQuery('document_srl',document_srl).setQuery('act','');
    if(comment_srl) url = url.setQuery('',comment_srl)+"#comment_"+comment_srl;*/
    
    var url = current_url.setQuery('act','').setQuery('comment_srl','')+'#comment_'+comment_srl;

    window.location.reload(true);
    window.location.href = url;
}


function completeDeleteComment(ret_obj) {
    var error = ret_obj['error'];
    var message = ret_obj['message'];
    var mid = ret_obj['mid'];
    var document_srl = ret_obj['document_srl'];
    var page = ret_obj['page'];

    var url = current_url.setQuery('mid',mid).setQuery('document_srl',document_srl).setQuery('act','');
    if(page) url = url.setQuery('page',page);

    location.href = url;

}


function doRecompileTree() {
    var params = new Array();
    params['mid'] = current_mid;
    exec_xml('xedocs','procXedocsRecompileTree', params);
}
