<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Plugin\Deriver\EntityInlineEntityForm.
 */

namespace Drupal\inline_entity_form\Plugin\Deriver;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @see \Drupal\inline_entity_form\Plugin\InlineEntityForm\EntityInlineEntityFormController
 */
class EntityInlineEntityForm implements ContainerDeriverInterface {

  /** @var \Drupal\Core\Entity\EntityManagerInterface */
  protected $entityManager;

  /**
   * The plugin derivatives keyed by derivative ID.
   *
   * @var array[]
   */
  protected $derivatives;

  /**
   * Constructs a new EntityInlineEntityForm instance.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static($container->get('entity.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinition($derivative_id, $base_plugin_definition) {
    if (!isset($this->derivatives)) {
      $this->getDerivativeDefinitions($base_plugin_definition);
    }
    if (isset($this->derivatives[$derivative_id])) {
      return $this->derivatives[$derivative_id];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    foreach ($this->entityManager->getDefinitions() as $entity_type_id => $entity_type) {
      $this->derivatives[$entity_type_id] = [
        'provider' => 'inline_entity_form',
        'title' => $entity_type->getLabel(),
        'class' => $base_plugin_definition['class'],
      ];
      $this->derivatives[$entity_type_id] += $base_plugin_definition;
    }
    return $this->derivatives;
  }

}

