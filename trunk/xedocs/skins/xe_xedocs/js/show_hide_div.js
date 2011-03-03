


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

      
      
      function showdiv1(id){
          

          div = document.getElementById(id);
          div.style.visibility="visible";
          div.style.display="block";
          div.style.width='30%';
          var id = div.id;
          document.getElementById('footer_links').style.width="70%";
          document.getElementById('rightcontent').style.width="";
          document.getElementById('rightcontent').style.position="";
          document.getElementById('rightcontent').style.left="";
          div = document.getElementById('e'+id);
         
          div.innerHTML = div.innerHTML.replace('+', '-');        

          div.onclick= function() { eval("hidediv1('"+ id+ "');"); };

        }


        function hidediv1(id){

            document.getElementById('rightcontent').style.width="99%";
            document.getElementById('footer_links').style.width="99%";
            
            //document.getElementById('rightcontent').style.position="absolute";
            //document.getElementById('rightcontent').style.left="-620px";
            
          div = document.getElementById(id);
          div.style.visibility="hidden";
          div.style.display="none";
          div.style.width='0px';
          var id = div.id;
          div = document.getElementById('e'+id);
          

          div.innerHTML = div.innerHTML.replace('-', '+');        
          div.onclick=function() { eval("showdiv1('"+ id+ "');"); };

        }
 