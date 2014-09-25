   </td>
</tr>
</table>
</body>
<script>
var cssmenu = document.getElementsByClassName("cssmenu");
function menuSelect(id) {
   var i;
   for (i = 0; i < cssmenu.length; i++) {
      if (cssmenu[i].id == id && cssmenu[i].style.display != "table") {
         cssmenu[i].style.display="table";
      } else {
         cssmenu[i].style.display="none";
      };
   };
};
</script>
</html>