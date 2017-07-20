<?php

namespace Drupal\asset_injector\Entity;

use Drupal;
use Drupal\asset_injector\AssetInjectorInterface;
use Drupal\asset_injector\AssetFileStorage;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Class AssetInjectorBase: Base asset injector class.
 *
 * @package Drupal\asset_injector\AssetInjectorBase.
 */
abstract class AssetInjectorBase extends ConfigEntityBase implements AssetInjectorInterface {

  /**
   * The Asset Injector ID.
   *
   * @var string
   */
  public $id;

  /**
   * The Js Injector label.
   *
   * @var string
   */
  public $label;

  /**
   * The code of the asset.
   *
   * @var string
   */
  public $code;

  /**
   * Themes to apply.
   *
   * @var array
   */
  public $themes;

  /**
   * Whitelist/blacklist pages.
   *
   * @var bool
   */
  public $visibility;

  /**
   * Pages to whitelist/blacklist.
   *
   * @var string
   */
  public $pages;

  /**
   * Node type to apply asset.
   *
   * @var string
   */
  public $nodeType;

  /**
   * {@inheritdoc}
   */
  public function isActive() {
    if (!$this->status()) {
      return FALSE;
    }

    $theme = Drupal::theme()->getActiveTheme()->getName();

    if (empty($this->themes) || in_array($theme, $this->themes)) {
      if (!empty($this->nodeType)) {
        $node = Drupal::routeMatch()->getParameter('node');
        if (is_object($node) && $node->getType() == $this->nodeType) {
          return TRUE;
        }
        else {
          return FALSE;
        }
      }

      $pages = rtrim($this->pages);
      if (empty($pages)) {
        return TRUE;
      }

      $path = Drupal::service('path.current')->getPath();
      $path_alias = Unicode::strtolower(Drupal::service('path.alias_manager')
        ->getAliasByPath($path));
      $page_match = Drupal::service('path.matcher')
        ->matchPath($path_alias, $pages) || (($path != $path_alias) && Drupal::service('path.matcher')
          ->matchPath($path, $pages));

      // When $rule->visibility has a value of 0, the asset is
      // added on all pages except those listed in $rule->pages.
      // When set to 1, it is added only on those pages listed in $rule->pages.
      if (!($this->visibility xor $page_match)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function libraryNameSuffix() {
    $extension = $this->extension();
    return "$extension/$this->id";
  }

  /**
   * {@inheritdoc}
   */
  abstract public function libraryInfo();

  /**
   * {@inheritdoc}
   */
  abstract public function extension();

  /**
   * {@inheritdoc}
   */
  public function internalFileUri() {
    $storage = new AssetFileStorage($this);
    return $storage->createFile();
  }

  /**
   * Get file path relative to drupal root to use in library info.
   *
   * @return string
   *   File path relative to drupal root, with leading slash.
   */
  protected function filePathRelativeToDrupalRoot() {
    // @todo See if we can simplify this via file_url_transform_relative().
    $path = parse_url(file_create_url($this->internalFileUri()), PHP_URL_PATH);
    $path = str_replace(base_path(), '/', $path);
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function getCode() {
    return $this->code;
  }

  /**
   * On delete delete this asset's file(s).
   */
  public function delete() {
    $storage = new AssetFileStorage($this);
    $storage->deleteFiles();
    parent::delete();
  }

  /**
   * On update delete this asset's file(s), will be recreated later.
   */
  public function preSave(EntityStorageInterface $storage) {
    $original_id = $this->getOriginalId();
    if ($original_id) {
      $original = $storage->loadUnchanged($original_id);
      // This happens to fail on config import.
      if ($original instanceof AssetInjectorInterface) {
        $asset_file_storage = new AssetFileStorage($original);
        $asset_file_storage->deleteFiles();
      }
    }
    parent::preSave($storage);
  }

}
