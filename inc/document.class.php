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

use GuzzleHttp\Psr7\MimeType;


/**
 * PluginProcessmakerDocument short summary.
 *
 * PluginProcessmakerDocument description.
 *
 * @author MoronO
 */
class PluginProcessmakerDocument extends CommonDBTM {

   /**
    * Summary of initPMSessionForCurrentUser
    * @param resource $ch
    * @return bool|string
    */
   private function initPMSessionForCurrentUser(&$ch) {
      global $PM_SOAP;
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_HEADER, 1);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

      curl_setopt($ch, CURLOPT_HTTPHEADER, ["Expect:"]);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $PM_SOAP->config['ssl_verify']);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $PM_SOAP->config['ssl_verify'] > 0 ? 2 : 0);

      curl_setopt($ch, CURLOPT_COOKIEFILE, "");

      // ### call to open case and get cookies ###
      $case = new PluginProcessmakerCase;
      $case->getFromDB($this->fields["plugin_processmaker_cases_id"]);
      curl_setopt($ch, CURLOPT_URL, $PM_SOAP->serverURL."/cases/cases_Open?sid=".$PM_SOAP->getPMSessionID()."&APP_UID=".$case->fields["case_guid"]."&DEL_INDEX=1&action=sent&glpi_init_case=1");
      return curl_exec($ch);
   }


   /**
    * Summary of getDocumentFromPM
    */
   public function getDocumentFromPM() {
      global $PM_SOAP;
      $response = '';

      header("Pragma: public"); // required
      header("Expires: 0");
      header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
      header("Cache-Control: private",false); // required for certain browsers

      $ch = curl_init();

      if ($this->initPMSessionForCurrentUser($ch)) {
         $url = $PM_SOAP->serverURL."/".$this->fields["link"]."&random=".rand();

         // ### second call to get PM document ###
         curl_setopt($ch, CURLOPT_HEADER, 0);

         curl_setopt($ch, CURLOPT_HTTPHEADER, ["Expect:"]);
         curl_setopt($ch, CURLOPT_URL, $url);

         $response = curl_exec ($ch);

         curl_close ($ch);


         $doc = new Document;
         $doc->getFromDB($this->fields["documents_id"]);

         header("Content-Type: " . $this->fields['mime']);
         header("Content-Disposition: attachment; filename=\"" . $doc->fields['name'] . "\";" );
      }

      header("Content-Transfer-Encoding: binary");
      header("Content-Length: " . strlen($response));

      echo $response;

   }


   /**
    * Summary of getDownloadURL
    * @return string
    */
   public function getDownloadURL() {
      global $CFG_GLPI;
      $downloadurl = Plugin::getWebDir('processmaker') . "/front/document.send.php?id=" . $this->getID();
      $ret = urldecode($CFG_GLPI["url_base"]."/index.php?redirect=$downloadurl");
      return $ret;
   }


   /**
    * Summary of addDocument
    * @param stdClass $doc
    * @param int $cases_id
    * @param int $entities_id
    * @param string $itemtype
    * @param int $items_id
    * @param int $users_id
    * @param bool $is_output false for input docs, and true for output docs
    * @return bool
    */
   public function addDocument(stdClass $doc, int $cases_id, int $entities_id, string $itemtype, int $items_id, int $users_id, bool $is_output) {
      $glpiDoc = new Document;
      $docitem = new Document_Item();
      // search for existing doc in glpi db
      if (!$this->getFromDBByCrit(['guid' => $doc->guid, 'version' => $doc->version])) {
         // then we must add this $doc
         $glpiDoc->add([
            'entities_id'  => $entities_id,
            'is_recursive' => 1,
            'name'         => $doc->filename,
            'users_id'     => $users_id,
            'mime'         => MimeType::fromFilename($doc->filename),
            ]);
         $this->add([
            'plugin_processmaker_cases_id' => $cases_id,
            'documents_id'                 => $glpiDoc->getID(),
            'guid'                         => $doc->guid,
            'version'                      => $doc->version,
            'link'                         => $doc->link,
            'mime'                         => $glpiDoc->fields['mime'],
            'is_output'                    => $is_output
            ]);
         $glpiDoc->update([
            'id'   => $glpiDoc->getID(),
            'link' => $this->getDownloadURL()
            ]);
         $docitem->add([
            'documents_id' => $glpiDoc->getID(),
            'items_id'     => $items_id,
            'itemtype'     => $itemtype,
            'entities_id'  => $entities_id,
            'is_recursive' => 1,
            'users_id'     => $users_id
            ]);

         return true;
      }

      return false;
   }

}