<?php

namespace Drupal\paragraphs_edit;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

class ParagraphEditForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $route_match = $this->getRouteMatch();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $route_match->getParameter('node');
    $field_name = $route_match->getParameter('field');
    $field = $node->get($field_name);
    $field_definition = $field->getFieldDefinition();
    $field_label = $field_definition->getLabel();
    $delta = $route_match->getParameter('delta');

    $form['#title'] = $this->t('Edit #@delta of @field of %label', [
      '@delta' => $delta,
      '@field' => $field_label,
      '%label' => $node->label()
    ]);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $route_match = $this->getRouteMatch();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $route_match->getParameter('node');
    $field = $route_match->getParameter('field');
    $delta = $route_match->getParameter('delta');

    $this->entity->save();

    $node->get($field)->get($delta)->setValue([
      'target_id' => $this->entity->id(),
      'target_revision_id' => $this->entity->getRevisionId()
    ]);

    return $node->save();
  }
}
