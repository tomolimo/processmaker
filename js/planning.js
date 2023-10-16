$(function () {
   $(document).ajaxComplete(function (event, jqXHR, ajaxOptions) {
      //debugger;
      if (!$('input[type="checkbox"][value="PluginProcessmakerTask"]').is(':checked')) {
         $('input[type="checkbox"][value="PluginProcessmakerTask"]').trigger('click');
      }
      $('input[type="checkbox"][value="PluginProcessmakerTask"]').parents('li').first().hide();
   });
});