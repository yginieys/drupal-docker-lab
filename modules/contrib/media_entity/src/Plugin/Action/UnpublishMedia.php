<?php

namespace Drupal\media_entity\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\media_entity\Entity\Media;

/**
 * Unpublishes a media entity.
 *
 * @Action(
 *   id = "media_unpublish_action",
 *   label = @Translation("Unpublish media"),
 *   type = "media"
 * )
 */
class UnpublishMedia extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(Media $entity = NULL) {
    $entity->setPublished(FALSE)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\media_entity\MediaInterface $object */
    $result = $object->access('update', $account, TRUE)
      ->andIf($object->status->access('edit', $account, TRUE));

    return $return_as_object ? $result : $result->isAllowed();
  }

}
