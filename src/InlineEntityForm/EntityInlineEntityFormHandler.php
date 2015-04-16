<?php

/**
 * Contains \Drupal\inline_entity_form\InlineEntityForm\EntityInlineEntityFormHandler.
 */

namespace Drupal\inline_entity_form\InlineEntityForm;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\InlineEntityFormHandlerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generic entity inline form handler.
 */
class EntityInlineEntityFormHandler implements InlineEntityFormHandlerInterface {

  /**
   * Entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * ID of entity type managed by this handler.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs the inline entity form controller.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   Entity manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   * @param string $entity_type_id
   *   ID of entity type managed by this handler.
   */
  public function __construct(EntityManagerInterface $entity_manager, ModuleHandlerInterface $module_handler, $entity_type_id) {
    $this->entityManager = $entity_manager;
    $this->moduleHandler = $module_handler;
    $this->entityTypeId = $entity_type_id;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('entity.manager'),
      $container->get('module_handler'),
      $entity_type->id()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function libraries() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function labels() {
    return [
      'singular' => t('entity'),
      'plural' => t('entities'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function tableFields($bundles) {
    $info = $this->entityManager->getDefinition($this->entityTypeId());
    $metadata = array();

    $fields = array();
    if ($info->hasKey('label')) {
      $label_key = $info->getKey('label');
      $fields[$label_key] = array(
        'type' => 'property',
        'label' => $metadata ? $metadata[$label_key]['label'] : t('Label'),
        'weight' => 1,
      );
    }
    else {
      $id_key = $info->getKey('id');
      $fields[$id_key] = array(
        'type' => 'property',
        'label' => $metadata ? $metadata[$id_key]['label'] : t('ID'),
        'weight' => 1,
      );
    }
    if (count($bundles) > 1) {
      $bundle_key = $info->getKey('bundle');
      $fields[$bundle_key] = array(
        'type' => 'property',
        'label' => $metadata ? $metadata[$bundle_key]['label'] : t('Type'),
        'weight' => 2,
      );
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function entityTypeId() {
    return $this->entityTypeId;
  }

  /**
   * {@inheritdoc}
   */
  public function entityForm($entity_form, FormStateInterface $form_state) {
    $operation = 'default';
    $controller = $this->entityManager->getFormObject($entity_form['#entity']->getEntityTypeId(), $operation);
    $controller->setEntity($entity_form['#entity']);
    $child_form_state = $this->buildChildFormState($controller, $form_state, $entity_form['#entity'], $operation);

    $entity_form = $controller->buildForm($entity_form, $child_form_state);

    if (!$entity_form['#display_actions']) {
      unset($entity_form['actions']);
    }

    // TODO - this is field-only part of the code. Figure out how to refactor.
    if ($child_form_state->get('inline_entity_form')) {
      foreach ($child_form_state->get('inline_entity_form') as $id => $data) {
        $form_state->set(['inline_entity_form', $id], $data);
      }
    }

    $form_state->set('field', $child_form_state->get('field'));

    $entity_form['#element_validate'][] = [get_class($this), 'entityFormValidate'];

    $entity_form['#ief_element_submit'][] = [get_class($this), 'entityFormSubmit'];
    $entity_form['#ief_element_submit'][] = [get_class($this), 'submitCleanFormState'];

    // Allow other modules to alter the form.
    $this->moduleHandler->alter('inline_entity_form_entity_form', $entity_form, $form_state);

    return $entity_form;
  }

  /**
   * {@inheritdoc}
   */
  public static function entityFormValidate($entity_form, FormStateInterface $form_state) {
    // We only do full entity validation if entire entity is to be saved, which
    // means it should be complete. Don't validate for other requests (like file
    // uploads, etc.).
    $triggering_element = $form_state->getTriggeringElement();
    if (!empty($triggering_element['#ief_submit_all'])) {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $entity_form['#entity'];
      $operation = 'default';

      $controller = \Drupal::entityManager()
        ->getFormObject($entity->getEntityTypeId(), $operation);
      $child_form_state = static::buildChildFormState($controller, $form_state, $entity, $operation);
      $controller->validate($entity_form, $child_form_state);

      foreach($child_form_state->getErrors() as $name => $message) {
        $form_state->setErrorByName($name, $message);
      }
    }

    // Unset un-triggered conditional fields errors
    $errors = $form_state->getErrors();
    $conditional_fields_untriggered_dependents = $form_state->get('conditional_fields_untriggered_dependents');
    if ($errors && !empty($conditional_fields_untriggered_dependents)) {
      foreach ($conditional_fields_untriggered_dependents as $untriggered_dependents) {
        if (!empty($untriggered_dependents['errors'])) {
          foreach (array_keys($untriggered_dependents['errors']) as $key) {
            unset($errors[$key]);
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function entityFormSubmit(&$entity_form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $entity_form['#entity'];
    $operation = 'default';

    $controller = \Drupal::entityManager()->getFormObject($entity->getEntityTypeId(), $operation);
    $controller->setEntity($entity);
    $child_form_state = static::buildChildFormState($controller, $form_state, $entity, $operation);

    $child_form = $entity_form;
    $child_form['#ief_parents'] = $entity_form['#parents'];

    $controller->submitForm($child_form, $child_form_state);
    $controller->save($child_form, $child_form_state);
    $entity_form['#entity'] = $controller->getEntity();

    if ($entity_form['#save_entity']) {
      $entity_form['#entity']->save();
    }

    // TODO - this is field-only part of the code. Figure out how to refactor.
    if ($child_form_state->get('inline_entity_form')) {
      foreach ($child_form_state->get('inline_entity_form') as $id => $data) {
        $data['entity'] = $entity_form['#entity'];
        $form_state->set(['inline_entity_form', $id], $data);
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function delete($ids, $context) {
    entity_delete_multiple($this->entityTypeId(), $ids);
  }

  /**
   * Build all necessary things for child form (form state, etc.).
   *
   * @param \Drupal\Core\Entity\EntityFormInterface $controller
   *   Entity form controller for child form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Parent form state object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object.
   * @param string $operation
   *   Operation that is to be performed in inline form.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   Child form state object.
   */
  public static function buildChildFormState(EntityFormInterface $controller, FormStateInterface $form_state, EntityInterface $entity, $operation) {
    $child_form_state = new FormState();

    $child_form_state->addBuildInfo('callback_object', $controller);
    $child_form_state->addBuildInfo('base_form_id', $controller->getBaseFormID());
    $child_form_state->addBuildInfo('form_id', $controller->getFormID());
    $child_form_state->addBuildInfo('args', array());

    // Copy values to child form.
    $child_form_state->setUserInput($form_state->getUserInput());
    $child_form_state->setValues($form_state->getValues());
    $child_form_state->setStorage($form_state->getStorage());

    $child_form_state->set('form_display', entity_load('entity_form_display', $entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $operation));

    // Since some of the submit handlers are run, redirects need to be disabled.
    $child_form_state->disableRedirect();

    // When a form is rebuilt after Ajax processing, its #build_id and #action
    // should not change.
    // @see drupal_rebuild_form()
    $rebuild_info = $child_form_state->getRebuildInfo();
    $rebuild_info['copy']['#build_id'] = TRUE;
    $rebuild_info['copy']['#action'] = TRUE;
    $child_form_state->setRebuildInfo($rebuild_info);

    $child_form_state->set('inline_entity_form', $form_state->get('inline_entity_form'));
    $child_form_state->set('langcode', $entity->language()->getId());

    $child_form_state->set('field', $form_state->get('field'));
    $child_form_state->setTriggeringElement($form_state->getTriggeringElement());
    $child_form_state->setSubmitHandlers($form_state->getSubmitHandlers());

    return $child_form_state;
  }

  /**
   * Cleans up the form state for a submitted entity form.
   *
   * After field_attach_submit() has run and the form has been closed, the form
   * state still contains field data in $form_state->get('field'). Unless that
   * data is removed, the next form with the same #parents (reopened add form,
   * for example) will contain data (i.e. uploaded files) from the previous form.
   *
   * @param $entity_form
   *   The entity form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public static function submitCleanFormState(&$entity_form, FormStateInterface $form_state) {
    $info = \Drupal::entityManager()->getDefinition($entity_form['#entity_type']);
    if (!$info->get('field_ui_base_route')) {
      // The entity type is not fieldable, nothing to cleanup.
      return;
    }

    $bundle = $entity_form['#entity']->bundle();
    $instances = \Drupal::entityManager()->getFieldDefinitions($entity_form['#entity_type'], $bundle);
    foreach ($instances as $instance) {
      $field_name = $instance->getName();
      if (!empty($entity_form[$field_name]['#parents'])) {
        $parents = $entity_form[$field_name]['#parents'];
        array_pop($parents);
        if (!empty($parents)) {
          $field_state = array();
          WidgetBase::setWidgetState($parents, $field_name, $form_state, $field_state);
        }
      }
    }
  }

}
