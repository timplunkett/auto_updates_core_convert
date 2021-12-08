<?php

namespace Tedbow\AutoUpdatesConvert;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * A class to do all the things.
 */
class TheClass {

  /**
   * @todo.
   */
  public static function getSetting(string $key):string {
    return static::getSettings()[$key];
  }

  /**
   * @todo.
   */
  public static function getCoreModulePath():string {
    return TheClass::getSetting('core_dir') . '/core/modules/auto_updates';
  }

  /**
   * @todo.
   */
  public static function replaceContents(string $search, string $replace) {
    $files = static::getDirContents(static::getCoreModulePath(), TRUE);
    foreach ($files as $file) {
      $filePath = $file->getRealPath();
      file_put_contents($filePath,str_replace($search,$replace,file_get_contents($filePath)));
    }

  }

  /**
   * @todo.
   */
  public static function renameFiles(string $old_pattern, string $new_pattern) {
    $files = static::getDirContents(static::getCoreModulePath());

    // Keep a record of the files and directories to change.
    // We will change all the files first so we don't change the location of any
    // of the files in the middle.
    // This probably won't work if we had nested folders with the pattern on 2
    // folder levels but we don't.
    $filesToChange = [];
    $dirsToChange = [];
    foreach ($files as $file) {
      $fileName = $file->getFilename();
      if ($fileName === '.') {
        $fullPath = $file->getPath();
        $parts = explode('/', $fullPath);
        $name = array_pop($parts);
        $path = "/" . implode('/', $parts);
      }
      else {
        $name = $fileName;
        $path = $file->getPath();
      }
      if (strpos($name, $old_pattern) !== FALSE) {
        $new_filename = str_replace($old_pattern, $new_pattern, $name);
        if ($file->isFile()) {
          $filesToChange[$file->getRealPath()] = $file->getPath() . "/$new_filename";
        }
        else {
          $dirsToChange[$file->getRealPath()] = "$path/$new_filename";
        }
      }
    }
    foreach ($filesToChange as $old => $new) {
      (new Filesystem())->rename($old, $new);
    }

    foreach ($dirsToChange as $old => $new) {
      (new Filesystem())->rename($old, $new);
    }
  }

  /**
   * @todo.
   */
  public static function getDirContents(string $path, $excludeDirs = FALSE):Array {
    $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

    $files = array();
    /** @var \SplFileInfo $file */
    foreach ($rii as $file) {
      if ($excludeDirs && $file->isDir()) {
        continue;
      }
      $files[] = $file;
    }

    return $files;
  }

  /**
   * @todo.
   */
  protected static function getSettings():string {
    static $settings;
    if (!$settings) {
      $settings = Yaml::parseFile(__DIR__ . '/../config.yml');
      $settings_keys = array_keys($settings);
      $require_settings = ['core_mr_branch', 'contrib_dir', 'core_dir'];
      $missing_settings = array_diff($require_settings, $settings_keys);
      if ($missing_settings) {
        throw new \Exception('Missing settings: ' . print_r($missing_settings,
            TRUE));
      }
    }

    return $settings;
  }

  /**
   * @todo.
   */
  public static function ensureGitClean():string {
    $status_output = shell_exec('git status');
    if (strpos($status_output, 'nothing to commit, working tree clean') === FALSE) {
      throw new \Exception("git not clean: " .$status_output);
    }
    return TRUE;
  }

  /**
   * @todo.
   */
  public static function getCurrentBranch():string {
    return trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
  }

  /**
   * @todo.
   */
  public static function switchToBranches() {
    $settings = static::getSettings();
    chdir($settings['contrib_dir']);
    static::switchToBranch('8.x-2.x');
    chdir($settings['core_dir']);
    static::switchToBranch($settings['core_mr_branch']);
  }

  /**
   * @todo.
   */
  public static function switchToBranch(string $branch) {
    static::ensureGitClean();
    shell_exec("git checkout $branch");
    if ($branch !== static::getCurrentBranch()) {
      throw new \Exception("could not check $branch");
    }
  }

  /**
   * @todo.
   */
  public static function makeCommit() {
    chdir(self::getSetting('contrib_dir'));
    self::ensureGitClean();
    $hash = trim(shell_exec('git rev-parse HEAD'));
    chdir(self::getSetting('core_dir'));
    shell_exec('git add core');
    shell_exec("git commit -m 'https://git.drupalcode.org/project/automatic_updates/-/commit/$hash'");
  }

}
