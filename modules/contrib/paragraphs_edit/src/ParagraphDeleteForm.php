<?php

namespace Drupal\paragraphs_edit;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for deleting a paragraph from a node.
 */
class ParagraphDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $route_match = $this->getRouteMatch();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $route_match->getParameter('node');
    $field_name = $route_match->getParameter('field');
    $field = $node->get($field_name);
    $field_definition = $field->getFieldDefinition();
    $field_label = $field_definition->getLabel();
    $delta = $route_match->getParameter('delta');

    return $this->t('Are you sure you want to delete #@delta of @field of %label?', [
      '@delta' => $delta,
      '@field' => $field_label,
      '%label' => $node->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    $route_match = $this->getRouteMatch();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $route_match->getParameter('node');
    $field_name = $route_match->getParameter('field');
    $field = $node->get($field_name);
    $field_definition = $field->getFieldDefinition();
    $field_label = $field_definition->getLabel();
    $delta = $route_match->getParameter('delta');

    return $this->t('Item #@delta of @field of %label has been deleted.', [
      '@delta' => $delta,
      '@field' => $field_label,
      '%label' => $node->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $route_match = $this->getRouteMatch();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $route_match->getParameter('node');
    $field = $route_match->getParameter('field');
    $delta = $route_match->getParameter('delta');

    $node->get($field)->removeItem($delta);
    $node->save();

    parent::submitForm($form, $form_state);
  }

}
