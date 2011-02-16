


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
