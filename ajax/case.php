<?php
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

use Glpi\Application\View\TemplateRenderer;
include ("../../../inc/includes.php");
$item = new $_REQUEST['itemtype'];
$item->getFromDB($_REQUEST['items_id']);
$countProcesses = [];
$cases = PluginProcessmakerCase::getAllCases($_REQUEST['itemtype'], $_REQUEST['items_id'], $countProcesses);
echo "<div class='row'>";
echo "<div class='col-auto order-last d-none d-md-block'>";
TemplateRenderer::getInstance()->display(
                'components\user\picture.html.twig',
                [
                    'users_id' => Session::getLoginUserID(),
                ]
            );
echo "</div>";
echo "<div class='col'>";
echo "<div class='row timeline-content t-right card mt-4' style='border: 1px solid rgb(65, 133, 244);'>";
echo "<div class='card-body'>";
echo "<div class='clearfix'>";
echo "<button class='btn btn-sm btn-ghost-secondary float-end mb-1 close-new-case-form collapsed' data-bs-toggle='collapse' data-bs-target='#new-CaseForm-block' aria-expanded='false'>";
echo "<i class='fa-lg ti ti-x'></i></button></div>";
echo "<div class='newCaseContent'>";
PluginProcessmakerCase::showAddFormForItem($item, rand(), $countProcesses, true);
echo "</div></div></div></div></div>";