$(function () {
   $(document).ajaxComplete(function (event, jqXHR, ajaxOptions) {
      //debugger;
      var pattern = /##processmaker.*(##|...)/g;
      
      $('tr.tab_bg_2 td a').each(function (index) {
         
         var textToChange = $(this).text();
         var matches = textToChange.match(pattern);
         if (matches) {
            textToChange = textToChange.replace(pattern, '');
            if (!textToChange.trim().length>0)
            {
               var title = $(this).parent().prev().text();
               textToChange = title;
            } 
            $(this).text(textToChange);
         }
      });
   });
});
