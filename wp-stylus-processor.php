<?php
/*
Plugin Name:    Wp Stylus Processor
Plugin URI:     http://www.wdgdc.com
Description:    A Plugin to detect when stylus files have been changed anywhere in the current theme and then process them.
Version:      0.1
Author:       David Everett
Author URI:     http://www.webdevelopmentgroup.com
License:          GPLv2 or later
Text Domain:    wp-stylus-processor
*/

use Stylus\Stylus;

require(dirname(__FILE__) .'/Stylus/Exception.php');
require(dirname(__FILE__) .'/Stylus/Stylus.php');

$arrFoundFiles = array();

function build_array($fullpath = null){
  global $arrFoundFiles;

  $arrFoundFiles[] = realpath($fullpath);
}

function find_files($path, $pattern, &$callback = array()) {
  $path = rtrim(str_replace("\\", "/", $path), '/') . '/';
  $matches = Array();
  $entries = Array();
  $dir = dir($path);
  while (false !== ($entry = $dir->read())) {
    $entries[] = $entry;
  }
  $dir->close();
  foreach ($entries as $entry) {
    $fullname = $path . $entry;
    if ($entry != '.' && $entry != '..' && is_dir($fullname)) {
      find_files($fullname, $pattern, $callback);
    } else if (is_file($fullname) && preg_match($pattern, $entry)) {
      $callback[] = realpath($fullname);
    }
  }

  return $callback;
}

if( !defined('NL') ) {
  define('NL', "\n");
}

if( !function_exists('checkTouch')  ){
  function checkTouch($strFlPth = false){
    if( !file_exists($strFlPth) ) {
      touch($strFlPth);

      chmod($strFlPth, 0777);
    } elseif(!is_writable($strFlPth) ) {
      unlink($strFlPth);
      touch($strFlPth);

      chmod($strFlPth, 0777);
    }

    return $strFlPth;
  }
}

if( !function_exists('checkGetPath') ){
  function checkGetPath($strPath){
    $strCachePath = realpath($strPath);

    if( !$strCachePath ){
      @mkdir($strPath, 0777, true);

      $strCachePath = realpath($strPath);
    }

    return $strCachePath;
  }
}

function get_preprocess_file_destination($strFilename = false){
  if(!$strFilename){
    return false;
  }

  $strChangeLogFilePath = checkGetPath(dirname(__FILE__) .'/../../stylus_change_logs/');
  $strChangeLogFilePath = checkGetPath(dirname(__FILE__) .'/');
  $strChangeLogSettings = checkTouch($strChangeLogFilePath .'/settings.json');

  $strChangeLogSettingContent = file_get_contents($strChangeLogSettings);

  $arrChangeLongSettings = json_decode($strChangeLogSettingContent, true);
  $arrChangeLongSettings = $arrChangeLongSettings;

  $strNewFile = str_replace(STYLESHEETPATH.'/', '', $strFilename);

  $arrFlNfo = pathinfo($strNewFile);

  if(isset($arrFlNfo['dirname']) && isset($arrChangeLongSettings['folder_destinations']) && isset($arrChangeLongSettings['folder_destinations'][ $arrFlNfo['dirname'] ])){
    $strToFolder = $arrChangeLongSettings['folder_destinations'][ $arrFlNfo['dirname'] ];

    $strNewFile = STYLESHEETPATH .'/'. $strToFolder .'/'. $arrFlNfo['basename'];
  //   // $arrChangeLongSettings['folder_destinations'][ $arrFlNfo['dirname'] ]
  } else {
    $strNewFile = $strFilename;
  }

  return $strNewFile;
}

function find_files_by_type($type = 'styl'){
  $strChangeLogFilePath = checkGetPath(dirname(__FILE__) .'/../../stylus_change_logs/');
  $strChangeLogFilePath = checkGetPath(dirname(__FILE__) .'/');
  $strChangeLogInfoFilepath = checkTouch($strChangeLogFilePath .'/changelog.srlz');
  $strChangeLogSettings = checkTouch($strChangeLogFilePath .'/settings.json');

  // $strDebugFilePath = checkTouch($strChangeLogFilePath .'/debug.txt');

  $strChangeLogContent = file_get_contents($strChangeLogInfoFilepath);

  $arrFilesToProcess = array();
  if(!@unserialize($strChangeLogContent)){
    $arrChangeLogs = array();
  } else {
    $arrChangeLogs = unserialize($strChangeLogContent);
  }

  $arrFoundFiles = find_files(STYLESHEETPATH .'/', "/\\.$type\$/");

  foreach($arrFoundFiles as $strFilePath){
    $strFileDtm = filemtime($strFilePath);

    if( !isset($arrChangeLogs[$strFilePath]) ){
      $arrChangeLogs[$strFilePath] = array(
        'dtm' => null,
        'status' => 'new'
      );
      $arrFilesToProcess[] = $strFilePath;
    } else {
      if($arrChangeLogs[$strFilePath]['dtm'] != $strFileDtm){
        $arrChangeLogs[$strFilePath]['status'] = 'updated';
        $arrFilesToProcess[] = $strFilePath;
      } else {
        $arrChangeLogs[$strFilePath]['status'] = 'NOT updated';
        // $arrFilesToProcess[] = $strFilePath;
      }
    }

    $arrChangeLogs[$strFilePath]['dtm'] = $strFileDtm;
  }

  // file_put_contents($strDebugFilePath, print_r($arrChangeLogs, true));

  if( !empty($arrFilesToProcess) ){
    $stylus = new Stylus();

    $arrTmp = array();
    foreach($arrFilesToProcess as $strFlPth){
      $destination = get_preprocess_file_destination($strFlPth);
      $arrPathInfo = pathinfo($destination);
      $arrFileInfo = pathinfo($strFlPth);

      $stylus->setReadDir($arrFileInfo['dirname']);
      $stylus->setWriteDir($arrPathInfo['dirname']);

      $stylus->fromFile($arrFileInfo['basename'])->toFile($arrPathInfo['filename'] .'.css', true);
    }
  }


  // file_put_contents($strDebugFilePath, print_r($arrChangeLogs, true));
  file_put_contents($strChangeLogInfoFilepath, serialize($arrChangeLogs));
}

add_action('wp_head', 'launch_find_files');
function launch_find_files(){
  find_files_by_type('styl');
}

