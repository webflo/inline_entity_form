<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Element\InlineEntityForm.
 */

namespace Drupal\inline_entity_form\Element;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element\RenderElement;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an inline entity form element.
 *
 * @RenderElement("inline_entity_form")
 */
class InlineEntityForm extends RenderElement implements ContainerFactoryPluginInterface {

  /**
   * Uuid generator service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   Uuid generator service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UuidInterface $uuid) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uuid')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#language' => LanguageInterface::LANGCODE_DEFAULT,
      '#ief_id' => $this->uuid->generate(),
      '#entity' => NULL,
      '#entity_type' => NULL,
      '#bundle' => NULL,
      '#op' => 'add',
      '#process' => [
        [$class, 'processEntityForm'],
      ],
      '#theme_wrappers' => ['container'],
    ];
  }

  /**
   * Uses inline entity form handler to add inline form to the structure.
   *
   * @param array $element
   *   An associative array containing the properties of the element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   *
   * @see self::preRenderAjaxForm()
   */
  public static function processEntityForm($element, FormStateInterface $form_state, &$complete_form) {
    if (empty($element['#entity_type']) && !empty($element['#entity']) && $element['#entity'] instanceof EntityInterface) {
      $element['#entity_type'] = $element['#entity']->entityTypeId();
    }

    if (empty($element['#bundle']) && !empty($element['#entity']) && $element['#entity'] instanceof EntityInterface) {
      $element['#bundle'] = $element['#entity']->bundle();
    }

    // We can't do anything useful if we don't know which entity type/ bundle
    // we're supposed to operate with.
    if (empty($element['#entity_type']) || empty($element['#bundle'])) {
      return;
    }

    /** @var \Drupal\inline_entity_form\InlineEntityFormHandlerInterface $ief_handler */
    $ief_handler = \Drupal::entityManager()->getHandler($element['#entity_type'], 'inline entity form');

    // IEF handler is a must. If one was not assigned to this entity type we can
    // not proceed.
    if (empty($ief_handler)) {
      return;
    }

    // If entity object is not there we're displaying the add form. We need to
    // create a new entity to be used with it.
    if (empty($element['#entity'])) {
      if ($element['#op'] == 'add') {
        $values = [
          'langcode' => $element['#language'],
        ];

        $bundle_key = \Drupal::entityManager()
          ->getDefinition($element['#entity_type'])
          ->getKey('bundle');

        if ($bundle_key) {
          $values[$bundle_key] = $element['#bundle'];
        }

        $element['#entity'] = \Drupal::entityManager()
          ->getStorage($element['#entity_type'])
          ->create($values);
      }
      else {
        // TODO - this structure relies on stuff from field. Fix it.
        $element['#entity'] = $form_state->get(['inline_entity_form', $element['#ief_id'], 'entity', $element['#ief_row_delta'], 'entity']);
      }
    }

    $element += $ief_handler->entityForm($element, $form_state);

    // Used by Field API and controller methods to find the relevant
    // values in $form_state.
    // TODO - this structure relies on stuff from field. Fix it.
    //$element['#parents'] = array_merge($element['#parents'], ['entities', $element['#ief_row_delta'], 'form']);

    return $element;
  }

}
