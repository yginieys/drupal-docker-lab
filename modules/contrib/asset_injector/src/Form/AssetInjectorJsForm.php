<?php

namespace Drupal\asset_injector\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Class AssetInjectorJsForm.
 *
 * @package Drupal\asset_injector\Form
 */
class AssetInjectorJsForm extends AssetInjectorFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\asset_injector\Entity\AssetInjectorJs $entity */
    $entity = $this->entity;
    $form['advanced']['jquery'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include jQuery'),
      '#description' => $this->t('Not all pages load jQuery by default. Select this to include jQuery when loading this asset.'),
      '#options' => [
        0 => $this->t('No'),
        1 => $this->t('Yes'),
      ],
      '#default_value' => $entity->jquery,
    ];

    $form['advanced']['preprocess'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preprocess JS'),
      '#description' => $this->t('If the JS is preprocessed, and JS aggregation is enabled, the script file will be aggregated.'),
      '#default_value' => $entity->preprocess,
    ];

    $form['advanced']['header'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load the script in the header of the page'),
      '#default_value' => $entity->header,
    ];

    $form['code']['#attributes']['data-ace-mode'] = 'javascript';
    return $form;
  }

}
