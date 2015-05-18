<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Controller\AutocompleteController.
 */

namespace Drupal\inline_entity_form\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Defines the autocompletion controller method.
 */
class AutocompleteController implements ContainerInjectionInterface {

  /**
   * Entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Selection manager service.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionManager;

  /**
   * Constructs a new AutocompleteController object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   */
  public function __construct(EntityManagerInterface $entity_manager, SelectionPluginManagerInterface $selection_manager) {
    $this->entityManager = $entity_manager;
    $this->selectionManager = $selection_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('plugin.manager.entity_reference_selection')
    );
  }

  /**
   * Handles the response for inline entity form autocompletion.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function autocomplete($entity_type_id, $field_name, $bundle, Request $request) {
    $string = $request->query->get('q');

    $fields = $this->entityManager->getFieldDefinitions($entity_type_id, $bundle);

    $field = $fields[$field_name];
    $storage = $field->getFieldStorageDefinition();
    $controller = inline_entity_form_get_controller($field);
    $widget = $this->entityManager
      ->getStorage('entity_form_display')
      ->load($entity_type_id . '.' . $bundle . '.default')
      ->getComponent($field_name);

    // The current entity type is not supported, or the string is empty.
    // strlen() is used instead of empty() since '0' is a valid value.
    if (!$field || !$storage || !$controller || !strlen($string)) {
      throw new AccessDeniedHttpException();
    }

    $results = array();
    if ($field->getType() == 'entity_reference') {
      /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler */
      $handler = $this->selectionManager->getSelectionHandler($field);
      $entity_labels = $handler->getReferenceableEntities($string, $widget['settings']['match_operator'], 10);

      foreach ($entity_labels as $bundle => $labels) {
        // Loop through each entity type, and autocomplete with its titles.
        foreach ($labels as $entity_id => $label) {
          // entityreference has already check_plain-ed the title.
          $results[] = t('!label (!entity_id)', array('!label' => $label, '!entity_id' => $entity_id));
        }
      }
    }

    $matches = array();
    foreach ($results as $result) {
      // Strip things like starting/trailing white spaces, line breaks and tags.
      $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($result)))));
      $matches[] = ['value' => $key, 'label' => '<div class="reference-autocomplete">' . $result . '</div>'];
    }

    return new JsonResponse($matches);
  }

}
