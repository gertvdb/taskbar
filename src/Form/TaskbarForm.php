<?php

namespace Drupal\taskbar\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class TaskbarForm.
 *
 * @package Drupal\taskbar\Form
 */
class TaskbarForm extends ConfigFormBase {

  use StringTranslationTrait;

  const CONFIG_NAME = 'taskbar.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taskbar_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $formState, Request $request = NULL) {
    $config = $this->config(self::CONFIG_NAME);

    $form['local_tasks'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Local Tasks'),
      '#description' => $this->t('Setting for the local tasks.'),
    ];

    $form['local_tasks']['local_tasks_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show local tasks in toolbar'),
      '#default_value' => $config->get('local_tasks_active'),
    ];

    return parent::buildForm($form, $formState);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState) {
    $config = $this->config(self::CONFIG_NAME);
    $config->set('local_tasks_active', $formState->getValue('local_tasks_active'));
    $config->save();
    parent::submitForm($form, $formState);
  }

}
