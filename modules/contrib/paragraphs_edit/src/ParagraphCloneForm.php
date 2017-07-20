<?php

namespace Drupal\paragraphs_edit;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

class ParagraphCloneForm extends ContentEntityForm {

  /**
   * The entity being cloned by this form.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $originalEntity;

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    parent::prepareEntity();

    $account = \Drupal::currentUser();
    $uuid_generator = \Drupal::service('uuid');

    // Create clone of this entity
    $this->originalEntity = clone $this->entity;

    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $paragraph = $this->entity;

    // Make sure this paragraph is treated as a new one.
    $paragraph->enforceIsNew();
    $paragraph->setNewRevision(TRUE);
    $paragraph->set('id', NULL);
    $paragraph->set('revision_id', NULL);
    $paragraph->set('uuid', $uuid_generator->generate());
    $paragraph->set('created', REQUEST_TIME);
    $paragraph->setOwnerId($account->id());
    $paragraph->setRevisionAuthorId($account->id());
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $route_match = $this->getRouteMatch();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $route_match->getParameter('node');
    $field_name = $route_match->getParameter('field');
    $field = $node->get($field_name);
    $field_definition = $field->getFieldDefinition();
    $field_label = $field_definition->getLabel();
    $delta = $route_match->getParameter('delta');

    $wrapper_id = Html::getUniqueId('paragraphs-edit-clone');

    $form['paragraphs_edit'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Clone to'),
      '#id' => $wrapper_id,
      '#tree' => TRUE,
    ];

    $potential_destinations = $this->getPotentialCloneDestinations($this->entity->bundle());

    $form['paragraphs_edit']['bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => $potential_destinations['bundles'],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::paragraphEditChangeAjax',
        'wrapper' => $wrapper_id,
      ],
    ];

    $selection_settings = [];
    $bundle = $form_state->getValue(['paragraphs_edit', 'bundle'], NULL);
    if (!empty($bundle)) {
      $selection_settings['target_bundles'] = [$bundle];
    }

    $form['paragraphs_edit']['parent'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Parent'),
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#selection_settings' => $selection_settings,
      '#required' => TRUE,
      '#disabled' => empty($bundle),
    ];

    $form['paragraphs_edit']['field'] = [
      '#type' => 'select',
      '#title' => $this->t('Field'),
      '#options' => !empty($bundle) ? $potential_destinations['fields'][$bundle] : [],
      '#required' => TRUE,
      '#disabled' => empty($bundle),
    ];
    if (count($form['paragraphs_edit']['field']['#options']) == 1) {
      $form['paragraphs_edit']['field']['#default_value'] = key($form['paragraphs_edit']['field']['#options']);
    }

    $form = parent::form($form, $form_state);
    $form['#title'] = $this->t('Clone #@delta of @field of %label', [
      '@delta' => $delta,
      '@field' => $field_label,
      '%label' => $node->label()
    ]);
    return $form;
  }

  public function paragraphEditChangeAjax($form) {
    return $form['paragraphs_edit'];
  }

  protected function getPotentialCloneDestinations($paragraph_type) {
    $bundles_labels = node_type_get_names();
    $types_with_paragraphs = $this->entityManager->getFieldMapByFieldType('entity_reference_revisions');
    $field_definitions_bundle = [];
    $destinations = [];
    foreach ($types_with_paragraphs['node'] as $field => $info) {
      foreach ($info['bundles'] as $bundle) {
        if (!isset($field_definitions_bundle[$bundle])) {
          $field_definitions_bundle[$bundle] = $this->entityManager->getFieldDefinitions('node', $bundle);
        }
        /** @var \Drupal\field\FieldConfigInterface $field_definition */
        $field_definition = $field_definitions_bundle[$bundle][$field];

        $destinations['bundles'][$bundle] = $bundles_labels[$bundle];
        $destinations['fields'][$bundle][$field] = $field_definition->getLabel();
      }
    }
    return $destinations;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $destination_entity_id = $form_state->getValue(['paragraphs_edit', 'parent']);
    $destination_field = $form_state->getValue(['paragraphs_edit', 'field']);
    if ($destination_entity_id && $destination_field) {
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $destination_entity */
      $destination_entity = $this->entityManager->getStorage('node')->load($destination_entity_id);
      if ($destination_entity) {
        if (!$destination_entity->access('update')) {
          $form_state->setError($form['paragraphs_edit']['parent'], 'You are not allowed to update this content.');
        }
        if (!$destination_entity->get($destination_field)->access('edit')) {
          $form_state->setError($form['paragraphs_edit']['field'], 'You are not allowed to edit this field.');
        }
      }
    }

    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $route_match = $this->getRouteMatch();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $route_match->getParameter('node');
    $field_name = $route_match->getParameter('field');
    $field = $node->get($field_name);
    $field_definition = $field->getFieldDefinition();
    $field_label = $field_definition->getLabel();
    $delta = $route_match->getParameter('delta');

    $destination_entity_id = $form_state->getValue(['paragraphs_edit', 'parent']);

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $destination_entity */
    $destination_entity = $this->entityManager->getStorage('node')->load($destination_entity_id);
    $destination_field = $form_state->getValue(['paragraphs_edit', 'field']);

    $this->entity->save();
    $destination_entity->get($destination_field)->appendItem([
      'target_id' => $this->entity->id(),
      'target_revision_id' => $this->entity->getRevisionId(),
    ]);

    //$destination->get($destination_field)->appendItem($this->entity);

    $destination_entity->save();

    drupal_set_message($this->t('Cloned #@delta of @field of %label to %destination_label', [
      '@delta' => $delta,
      '@field' => $field_label,
      '%label' => $node->label(),
      '%destination_label' => $destination_entity->label()
    ]));

    $request = \Drupal::request();
    if ($request->query->has('destination')) {
      $request->query->remove('destination');
    }
    $form_state->setRedirectUrl($destination_entity->toUrl());
  }

}
