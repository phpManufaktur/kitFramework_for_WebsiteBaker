<?php

/**
 * kitConnect
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://kit2.phpmanufaktur.de
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\kitFramework\unZip;

use phpManufaktur\kitFramework\unZip\unZipException;

if (!defined('WB_PATH'))
	exit('Can\'t access this file directly!');

require_once WB_PATH.'/modules/kit_framework/Setup/unZip/unZipException.php';

class unZip {

  protected static $unzip_path = null;
  protected static $use_pclzip = false;
  protected $pclzip = null;
  protected static $file_list = array();

  /**
   * Constructor for the class UnZip
   */
  public function __construct() {
    // ZipArchive is the preferred method
    if (!class_exists('ZipArchive')) {
      // check if ziblib is installed ...
      if (!function_exists('gzopen')) {
        // no more chance ...
        throw unZipException::error('Missing the ZipArchive or the zlib extension - can\'t unzip any file!');
      }
      self::$use_pclzip = true;
    }
    self::$file_list = array();
    // set the unzip path
    self::$unzip_path = WB_PATH.'/temp/unzip';
    // check directory and create it if necessary
    $this->checkDirectory(self::$unzip_path, true);
  } // __construct()

  /**
   * Check if the desired $path exists and try to create it also with nested
   * subdirectories if $create is true
   *
   * @param string $path
   * @param boolean $create try to create the directory
   * @throws UnZipException
   */
  public function checkDirectory($path, $create=true) {
    if (!file_exists($path)) {
      if ($create) {
        if (!mkdir($path, 0755, true)) {
          throw new unZipException(sprintf("Can't create the directory %s!", $path));
        }
      }
      else {
        throw new unZipException(sprintf('The directory %s does not exists!', $path));
      }
    }
  } // checkPath()

  /**
   * Iterate directory tree very efficient
   * Function postet from donovan.pp@gmail.com at
   * http://www.php.net/manual/de/function.scandir.php
   *
   * @param sting $dir
   * @return array - directoryTree
   */
  public static function directoryTree($dir) {
    if (substr($dir,-1) == "/")
      $dir = substr($dir,0,-1);
    $path = array();
    $stack = array();
    $stack[] = $dir;
    while ($stack) {
      $thisdir = array_pop($stack);
      if (false !== ($dircont = scandir($thisdir))) {
        $i=0;
        while (isset($dircont[$i])) {
          if ($dircont[$i] !== '.' && $dircont[$i] !== '..') {
            $current_file = "{$thisdir}/{$dircont[$i]}";
            if (is_file($current_file)) {
              $path[] = "{$thisdir}/{$dircont[$i]}";
            }
            elseif (is_dir($current_file)) {
              $stack[] = $current_file;
            }
          }
          $i++;
        }
      }
    }
    return $path;
  } // directoryTree()

  /**
   * Delete a directory recursivly
   *
   * @param string $directory_path
   */
  public function deleteDirectory($directory_path) {
    if (is_dir($directory_path)) {
      $items = scandir($directory_path);
      foreach ($items as $item) {
        if (($item != '.') && ($item != '..')) {
          if (filetype($directory_path.'/'.$item) == 'dir')
            $this->deleteDirectory($directory_path.'/'.$item);
          elseif (!unlink($directory_path.'/'.$item))
            throw new unZipException(sprintf('Can\'t delete the file %s.', $directory_path.'/'.$item));
        }
      }
      reset($items);
      if (!rmdir($directory_path))
        throw new unZipException(sprintf('Can\'t delete the directory %s.', $directory_path));
    }
  } // deleteDirectory()


  /**
   * Set the path for the unzip operation
   *
   * @param string $unzip_path
   */
  public static function setUnZipPath($unzip_path) {
    self::$unzip_path = $unzip_path;
  } // setUnZipPath()

  /**
   * Return the UnZip Path
   *
   * @return string path
   */
  public static function getUnZipPath() {
    return self::$unzip_path;
  } // getUnZipPath()

  /**
   * Return the list of the extracted files and directories
   *
   * @return array
   */
  public static function getFileList() {
    return self::$file_list;
  } // getFileList()

  /**
   * Create a list of the unzipped files
   *
   * @param array $list
   */
  protected function createPclZipFileList($list) {
    $file_list = array();
    foreach ($list as $item) {
      if ($item['folder'] == 1) continue;
      $file_list[] = array(
          'file_path' => $item['filename'],
          'file_name' => $item['stored_filename'],
          'file_size' => $item['size'],
          );
    }
    self::$file_list = $file_list;
  } // createPclZipFileList()

  /**
   * Create a list of the unzipped files
   */
  protected function createZipArchiveFileList() {
    $file_list = array();
    $list = $this->directoryTree(self::$unzip_path);
    foreach ($list as $file) {
      $file_list[] = array(
          'file_path' => $file,
          'file_name' => substr($file, strlen(self::$unzip_path)+1),
          'file_size' => filesize($file),
          );
    }
    self::$file_list = $file_list;
  } // createZipArchiveFileList()

  /**
   * Unzip the desired $zip_file and return a list with the extracted files
   *
   * @param string $zip_file
   * @throws UnZipException
   * @return array|boolean
   */
  public function extract($zip_file) {
    // delete the files and directories from the unzip path
    $this->deleteDirectory(self::$unzip_path);

    if (self::$use_pclzip) {
      // use PclZip for decompressing
      try {
        // require the PclZip library
        require_once __DIR__.'/pclzip/pclzip.lib.php';
        // set the temporary directory for pclzip
        if (!defined('PCLZIP_TEMPORARY_DIR'))
          define('PCLZIP_TEMPORARY_DIR', self::getUnZipPath);
        // create PclZip instance
        $this->pclzip = new \PclZip($zip_file);
        $list = $this->pclzip->extract(self::$unzip_path);
        if (!is_array($list)) {
          throw unZipException::error(sprintf('PclZip Error - Code: %d - Message: %s',
              $this->pclzip->error_code, $this->pclzip->error_string));
        }
        return true;
        $this->createPclZipFileList($list);
      } catch (\Exception $e) {
        throw unZipException::error($e->getMessage());
      }
    }

    // use ZipArchive for decrompressing
    try {
      $zipArchive = new \ZipArchive();
      if (true !== ($status = $zipArchive->Open($zip_file))) {
        throw unZipException::errorZipArchiveOpen($status, $zip_file);
      }
      if (!$zipArchive->extractTo(self::$unzip_path)) {
        throw unZipException::error(sprintf('Can\'t extract the archive to %s', self::$unzip_path));
      }
      // create the file list
      $this->createZipArchiveFileList();
      return true;
    } catch (\Exception $e) {
      throw unZipException::error($e->getMessage());
    }

    return false;
  } // extract()

} // class unZip