<?php

/**
 * Contains \Drupal\inline_entity_form\InlineEntityForm\EntityInlineEntityFormHandler.
 */

namespace Drupal\inline_entity_form\InlineEntityForm;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
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

    $entity_form['#ief_parents'] = $entity_form['#parents'];

    $entity_form = $controller->buildForm($entity_form, $child_form_state);

    foreach ($child_form_state->get('inline_entity_form') as $id => $data) {
      $form_state->set(['inline_entity_form', $id], $data);
    }

    $form_state->set('field', $child_form_state->get('field'));

    $entity_form['#element_validate'][] = [get_class($this), 'entityFormValidate'];
    $entity_form['#ief_element_submit'][] = 'inline_entity_form_entity_form_submit';

    // Allow other modules to alter the form.
    $this->moduleHandler->alter('inline_entity_form_entity_form', $entity_form, $form_state);

    return $entity_form;
  }

  /**
   * {@inheritdoc}
   */
  public static function entityFormValidate($entity_form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $entity_form['#entity'];

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = \Drupal::entityManager()->getStorage('entity_form_display')->load($entity->getEntityTypeId() . '.' . $entity->bundle() . '.default');
    $form_display->validateFormValues($entity, $entity_form, $form_state);

    // Unset un-triggered conditional fields errors
    $errors = $form_state->getErrors();
    $conditional_fields_untriggered_dependents = $form_state->get('conditional_fields_untriggered_dependents');
    if ($errors && !empty($conditional_fields_untriggered_dependents)) {
      foreach ($conditional_fields_untriggered_dependents as $untriggered_dependents ) {
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
  public function entityFormSubmit(&$entity_form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $entity_form['#entity'];
    $operation = 'default';

    $controller = $this->entityManager->getFormObject($entity->getEntityTypeId(), $operation);
    $controller->setEntity($entity);
    $child_form_state = $this->buildChildFormState($controller, $form_state, $entity, $operation);

    $child_form = $entity_form;
    $child_form['#ief_parents'] = $entity_form['#parents'];

    $controller->submitForm($child_form, $child_form_state);
    $controller->save($child_form, $child_form_state);
    $entity_form['#entity'] = $controller->getEntity();

    foreach ($child_form_state->get('inline_entity_form') as $id => $data) {
      $form_state->set(['inline_entity_form', $id], $data);
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
  protected function buildChildFormState(EntityFormInterface $controller, FormStateInterface $form_state, EntityInterface $entity, $operation) {
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
    $child_form_state->set('langcode', $entity->langcode->value);

    $child_form_state->set('field', $form_state->get('field'));
    $child_form_state->setTriggeringElement($form_state->getTriggeringElement());
    $child_form_state->setSubmitHandlers($form_state->getSubmitHandlers());

    return $child_form_state;
  }

}
