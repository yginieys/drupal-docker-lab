<?php

namespace Drupal\icons;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\ContextAwarePluginBase;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Plugin\PluginWithFormsTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Component\Transliteration\TransliterationInterface;

/**
 * Defines a base icon set implementation that most icon library plugins extend.
 *
 * This abstract class provides the generic icon provider configuration form,
 * default icon provider settings, and handling for general user-defined icon
 * provider visibility settings.
 *
 * @ingroup icons
 */
abstract class IconLibraryPluginBase extends ContextAwarePluginBase implements IconLibraryPluginInterface, PluginWithFormsInterface {

  use ContextAwarePluginAssignmentTrait;
  use PluginWithFormsTrait;

  /**
   * The transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliteration;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $definition = $this->getPluginDefinition();
    // Cast the admin label to a string since it is an object.
    // @see \Drupal\Core\StringTranslation\TranslatableMarkup
    return (string) $definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description() {
    $definition = $this->getPluginDefinition();
    // Cast the admin label to a string since it is an object.
    // @see \Drupal\Core\StringTranslation\TranslatableMarkup
    return (string) $definition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep(
      $this->baseConfigurationDefaults(),
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * Returns generic default configuration for icon provider plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function baseConfigurationDefaults() {
    return array(
      'id' => $this->getPluginId(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfigurationValue($key, $value) {
    $this->configuration[$key] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, $return_as_object = FALSE) {
    $access = $this->iconLibraryAccess();
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * Indicates whether the icon set should be shown.
   *
   * Icon sets with specific access checking should override this method rather
   * than access(), in order to avoid repeating the handling of the
   * $return_as_object argument.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   *
   * @see self::access()
   */
  protected function iconLibraryAccess(AccountInterface $account) {
    // By default, the icon provider is visible.
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   *
   * Creates a generic configuration form for all icon set types. Individual
   * icon library plugins can add elements to this form by overriding
   * IconLibraryPluginBase::iconLibraryForm(). Most icon library plugins should
   * not override this method unless they need to alter the generic form
   * elements.
   *
   * @see \Drupal\icons\IconProviderPluginBase::iconProviderForm()
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $definition = $this->getPluginDefinition();

    $form['#title'] = $definition['label'];

    $form['description'] = array(
      '#type' => 'markup',
      '#markup' => $definition['description'],
    );

    // Add plugin-specific settings for this icon set.
    $form += $this->iconLibraryForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function iconLibraryForm(array $form, FormStateInterface $form_state) {
    return array();
  }

  /**
   * {@inheritdoc}
   *
   * Most icon library plugins should not override this method. To add
   * validation for a specific icon set, override
   * IconLibraryPluginBase::iconLibraryValidate().
   *
   * @see \Drupal\icons\IconLibraryPluginBase::iconLibraryValidate()
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->iconLibraryValidate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function iconLibraryValidate(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   *
   * Most icon library plugins should not override this method. To add
   * submission handling for a specific icon set, override
   * IconLibraryPluginBase::iconLibrarySubmit().
   *
   * @see \Drupal\icons\IconProviderPluginBase::iconProviderSubmit()
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Process the icon library submission handling if no errors occurred only.
    if (!$form_state->getErrors()) {
      $this->iconLibrarySubmit($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function iconLibrarySubmit(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function getMachineNameSuggestion() {
    $definition = $this->getPluginDefinition();
    $label = $definition['label'];

    // @todo This is basically the same as what is done in
    //   \Drupal\system\MachineNameController::transliterate(), so it might make
    //   sense to provide a common service for the two.
    $transliterated = $this->transliteration()
      ->transliterate($label, LanguageInterface::LANGCODE_DEFAULT, '_');
    $transliterated = Unicode::strtolower($transliterated);

    $transliterated = preg_replace('@[^a-z0-9_.]+@', '', $transliterated);

    return $transliterated;
  }

  /**
   * Wraps the transliteration service.
   *
   * @return \Drupal\Component\Transliteration\TransliterationInterface
   *   Returns the transliteration service.
   */
  protected function transliteration() {
    if (!$this->transliteration) {
      $this->transliteration = \Drupal::transliteration();
    }
    return $this->transliteration;
  }

  /**
   * Sets the transliteration service.
   *
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   The transliteration service.
   */
  public function setTransliteration(TransliterationInterface $transliteration) {
    $this->transliteration = $transliteration;
  }

}
