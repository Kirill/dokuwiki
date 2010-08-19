#!/usr/bin/php
<?php
// This script reminds users to check their sites for validity by sending an email if 
// the last change of a site is longer in the past than $conf['revalidation_interval']
// and the time difference is not greater than $conf['revalidation_interval_delta']
//
// If your auth backend requires a username/password to retrieve the user details
// this information has to be set in the config (for example auth-ad)
//
// If the script is unable to determine the users details it sends the email to the 
// email address specified in $conf['mailfrom']
//
// Put the following options in your dokuwiki config file:
//
// $conf['revalidation_interval']  = 365;   //interval in days in which users are asked to re-validate the content their sites
// $conf['revalidation_interval_delta']  = 1.1;   //time in days, that the time can differ from the interval specified
// $conf['revalidation_url']  = "https://dokuwiki.example.com/wiki/doku.php?id=";   //the url prefix for the link in the mail
//
// code by Alexander Ganster

if ('cli' != php_sapi_name()) die();
    
ini_set('memory_limit','128M');
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../').'/');
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/common.php');
require_once(DOKU_INC.'inc/pageutils.php');
require_once(DOKU_INC.'inc/search.php');
require_once(DOKU_INC.'inc/auth.php');
require_once(DOKU_INC.'inc/mail.php');
require_once(DOKU_INC.'inc/cliopts.php');
session_write_close();

// load the the backend auth functions and instantiate the auth object
if (@file_exists(DOKU_INC.'inc/auth/'.$conf['authtype'].'.class.php')) {
   require_once(DOKU_INC.'inc/auth/basic.class.php');
   require_once(DOKU_INC.'inc/auth/'.$conf['authtype'].'.class.php');

   $auth_class = "auth_".$conf['authtype'];
   if (class_exists($auth_class)) {
        $auth = new $auth_class();
   }
}

$pages = file($conf['indexdir'].'/page.idx');
$pages = array_filter($pages, 'isVisiblePage'); // discard hidden pages

$cnt = count($pages);
for($i = 0; $i < $cnt; $i++){
   if(!page_exists($pages[$i])){
       unset($pages[$i]);
       continue;
   }
   else {
      $ID = $pages[$i];
      $INFO = pageinfo();
      echo trim($ID) . "      > last change: ".date('Y-m-d H:i:s',$INFO['lastmod'])."\n";

      //set some variables for convenience
      $lastmod = $INFO['lastmod'];
      $reval_int = $conf['revalidation_interval'] *24*60*60;
      $reval_delta = $conf['revalidation_interval_delta'] *24*60*60;

      //check if lastmod date is newer than the revalidation interval
      if ( time() < $INFO['lastmod'] + $reval_int ) { 
         echo "   newer than the revalidation interval\n"; //debug
         continue;
      }

      //calculate the difference to the interval
      $reval_tmp = (time()-$lastmod) % $reval_int;
      
      //if the interval difference is smaller than the allowed delta time; send mail
      if ( ($reval_tmp <= $reval_delta) ) {
         $USERINFO = $auth->getUserData($INFO['user']);
         $subject = $conf['title']." - Please revalidate your page for correctness";

         // if we don't have the users email address, send mail to admin
         if ( !isset($USERINFO['mail']) ) {
            $INFO['user'] = "Admin";
            $USERINFO['mail'] = $conf['mailfrom'];
         }

         $body = prepare_mailbody();
         mail_send($USERINFO['mail'], $subject, $body, $conf['mailfrom']);
         //echo $body; //debug
         //echo "   mail sent to ".$USERINFO['mail']."\n"; //debug
      }
      else {
         //echo "   interval is not near...\n"; //debug
      }
   }
}


function  prepare_mailbody() {
   global $ID;
   global $conf;
   global $INFO;
   global $USERINFO;
 
   $body = "Dear ".$USERINFO['name'].",

Please validate if the following page which you saved in ".$conf['title']." is still valid:

Site: ".trim($ID)."
   Link: ".$conf['revalidation_url'].trim($ID)."
   Editor: ".$INFO['user']."
   Last change: " . date('Y-m-d H:i:s',$INFO['lastmod'])." 
   
If the information on this site is not needed anymore please delete it.

Thanks,
".$conf['title']."
";
   return $body;
}


?>
