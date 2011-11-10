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

    var url = current_url.setQuery('mid',mid).setQuery('document_srl',document_srl).setQuery('act','dispXedocsCommentEditor');
    if(comment_srl) url = url.setQuery('rnd',comment_srl)+"comment_"+comment_srl;

    location.href = url;
}


function completeDeleteComment(ret_obj) {
    var error = ret_obj['error'];
    var message = ret_obj['message'];
    var mid = ret_obj['mid'];
    var document_srl = ret_obj['document_srl'];
    var page = ret_obj['page'];

    var url = current_url.setQuery('mid',mid).setQuery('document_srl',document_srl).setQuery('act','dispXedocsCommentEditor');
    if(page) url = url.setQuery('page',page);

    location.href = url;
    
}


function doRecompileTree() {
    var params = new Array();
    params['mid'] = current_mid;
    exec_xml('xedocs','procXedocsRecompileTree', params);
}
