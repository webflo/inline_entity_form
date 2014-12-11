<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\AutocompleteController.
 */

namespace Drupal\inline_entity_form;
use Drupal\Component\Utility\String;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Defines the autocompletion controller method.
 */
class AutocompleteController implements ContainerInjectionInterface {

  /** @var \Drupal\Core\Entity\EntityManagerInterface  */
  protected $entityManager;

  /**
   * Constructsa a new AutocompleteController object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')
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
    $settings = $storage->getSettings();
    $controller = inline_entity_form_get_controller($field);
    // The current entity type is not supported, or the string is empty.
    // strlen() is used instead of empty() since '0' is a valid value.
    if (!$field || !$storage || !$controller || !strlen($string)) {
      throw new AccessDeniedHttpException();
    }

    $results = array();
    if ($field->getType() == 'commerce_product_reference') {
      $match_operator = strtolower($controller->getSetting('match_operator'));
      $products = commerce_product_match_products($field, $storage, $string, $match_operator, array(), 10, TRUE);

      // Loop through the products and convert them into autocomplete output.
      foreach ($products as $product_id => $data) {
        $results[] = t('@label (!entity_id)', array('@label' => $data['title'], '!entity_id' => $product_id));
      }
    }
    elseif ($field->getType() == 'entity_reference') {
      /** @var \Drupal\entity_reference\Plugin\Type\SelectionPluginManager $selection_manager */
      $selection_manager = \Drupal::service('plugin.manager.entity_reference.selection');
      /** @var \Drupal\entity_reference\Plugin\Type\Selection\SelectionInterface $handler */
      $handler = $selection_manager->getSelectionHandler($field);
      $entity_labels = $handler->getReferenceableEntities($string, $controller->getSetting('match_operator'), 10);

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
      $key = preg_replace('/\s\s+/', ' ', str_replace("\n", '', trim(String::decodeEntities(strip_tags($result)))));
      $matches[] = ['value' => $key, 'label' => '<div class="reference-autocomplete">' . $result . '</div>'];
    }

    return new JsonResponse($matches);
  }

}
