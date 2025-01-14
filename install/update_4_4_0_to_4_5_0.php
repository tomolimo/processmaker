<?php
/*
-------------------------------------------------------------------------
ProcessMaker plugin for GLPI
Copyright (C) 2014-2023 by Raynet SAS a company of A.Raymond Network.

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
function update_4_4_0_to_4_5_0() {
    global $DB;

    if (!$DB->tableExists('glpi_plugin_processmaker_taskrecalls')) {
        $query  = "CREATE TABLE `glpi_plugin_processmaker_taskrecalls` (
             `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
             `plugin_processmaker_tasks_id` INT UNSIGNED NOT NULL,
             `before_time` INT NOT NULL DEFAULT '-10',
             `after_time` INT NOT NULL DEFAULT '-10',
             `when` TIMESTAMP NULL DEFAULT NULL,
             `users_id` INT UNSIGNED NULL,
             PRIMARY KEY (`id`),
             UNIQUE INDEX `item` (`plugin_processmaker_tasks_id`),
             INDEX `when` (`when`)
           ) ENGINE=InnoDB;";
        $DB->query($query) or die("error when creating glpi_plugin_processmaker_taskrecalls table" . $DB->error());
    }

    if (!$DB->tableExists('glpi_plugin_processmaker_taskalerts')) {
        $query = "CREATE TABLE `glpi_plugin_processmaker_taskalerts` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `plugin_processmaker_taskrecalls_id` INT UNSIGNED NOT NULL,
            `date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `plugin_processmaker_taskrecalls_id` (`plugin_processmaker_taskrecalls_id`),
            INDEX `date` (`date`)
            ) ENGINE=InnoDB;";

        $DB->query($query) or die("error when creating glpi_plugin_processmaker_taskalerts table" . $DB->error());
    }

    if (!$DB->fieldExists('glpi_plugin_processmaker_taskcategories', 'reminder_recall_time')) {
        // add the field into table
        $query = "ALTER TABLE `glpi_plugin_processmaker_taskcategories`
                  ADD COLUMN `before_time` INT NOT NULL DEFAULT '-10',
                  ADD COLUMN `after_time` INT NOT NULL DEFAULT '-10',
                  ADD COLUMN `users_id` INT UNSIGNED NULL
                  ;";

        $DB->query($query) or die("error when adding reminder_recall_time field to glpi_plugin_processmaker_taskcategories table" . $DB->error());
    }

   return '4.5.0';
}