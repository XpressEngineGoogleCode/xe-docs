function showdiv(id){

	div = document.getElementById(id);
	div.style.visibility="visible";
	div.style.display="block";
	var id = div.id;
	div = document.getElementById('e'+id);

	div.innerHTML = div.innerHTML.replace('+', '-');        

	div.onclick= function() { eval("hidediv('"+ id+ "');"); };
}


function hidediv(id){

	div = document.getElementById(id);
	div.style.visibility="hidden";
	div.style.display="none";
	var id = div.id;
	div = document.getElementById('e'+id);

	div.innerHTML = div.innerHTML.replace('-', '+');        
	div.onclick=function() { eval("showdiv('"+ id+ "');"); };
}

function toggle_tooltip(div){      
	
	div = document.getElementById("showHideTree");
	
	if( -1 != div.getAttribute("alt").search("Close")){
		div.setAttribute("alt", "Show tree");
		div.setAttribute("title", "Show tree");
	}else{
		div.setAttribute("alt", "Close tree");
		div.setAttribute("title", "Close tree");
	}
}
      
function showdiv1(id){

	div = document.getElementById(id);
	div.style.visibility="visible";
	div.style.display="block";
	toggle_tooltip();
	
	//div.style.width='30%';
	var id = div.id;
	//document.getElementById('footer_links').style.width="70%";
	//document.getElementById('comments').style.width="70%";

	document.getElementById('rightcontent').style.width="";
	document.getElementById('rightcontent').style.position="";
	document.getElementById('rightcontent').style.left="";
	div = document.getElementById('showHideTree');

	div.onclick= function() { eval("hidediv1('"+ id+ "');"); };
	div.style.backgroundPosition = "0px 0px";
	div.style.left = "267px";
	
	
	
}


function hidediv1 (id) {

	div = document.getElementById(id);
	div.style.visibility="hidden";
	div.style.display="none";
	//div.style.width='0px';
	var id = div.id;
	div = document.getElementById('showHideTree');

	//div.innerHTML = div.innerHTML.replace('-', '+');        
	div.onclick=function() { eval("showdiv1('"+ id+ "');"); };
	div.style.backgroundPosition = "-13px 0px";
	div.style.left = "1px";
	
	toggle_tooltip();
}
 