<?php

/**
 * PluginProcessmakerCrontaskaction is used to manage actions between cases
 *
 * Allows actions: routing cases (called slaves) from another case (called master)
 *
 *
 * @version 1.0
 * @author MoronO
 */
class PluginProcessmakerCrontaskaction extends CommonDBTM {

   // postdatas are of the form:
   // {"form":{"RELEASE_DONE":"0","btnGLPISendRequest":"submit"},"UID":"28421020557bffc5b374850018853291","__DynaformName__":"51126098657bd96b286ded7016691792_28421020557bffc5b374850018853291","__notValidateThisFields__":"[]","DynaformRequiredFields":"[]","APP_UID":"6077575685836f7d89cabe6013770123","DEL_INDEX":"4"}


   const WAITING_DATAS = 1 ;
   const DATAS_READY = 2 ;
   const DONE = 3 ;

}