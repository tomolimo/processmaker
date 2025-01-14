/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2024 by Raynet SAS a company of A.Raymond Network.

https://www.araymond.com/
-------------------------------------------------------------------------

LICENSE

This file is part of ProcessMaker plugin for GLPI.

This file is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this plugin. If not, see <http://www.gnu.org/licenses/>.
--------------------------------------------------------------------------
 */
$(function () {
   $(document).ajaxComplete(function (event, jqXHR, ajaxOptions) {
      //debugger;
      var pattern = /##processmaker.*(##|...)/g;

      $('.grid-item table tbody tr td a').each(function (index) {
         var textToChange = $(this).text();
         var matches = textToChange.match(pattern);
         if (matches) {
            textToChange = textToChange.replace(pattern, '');
            if (!textToChange.trim().length>0) {
               var title = $(this).parent().prev().text();
               textToChange = title;
            }
            $(this).text(textToChange);
         }
      });
   });
});
