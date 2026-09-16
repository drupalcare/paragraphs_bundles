<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\node\NodeInterface;

/**
 * Fills the two dropdowns: what may be added, and what is already here.
 *
 * Both lists are built on the server from config and from the page itself, so
 * the model is never asked which section someone meant. It is handed a machine
 * name for the first list and a paragraph id for the second.
 */
interface SectionCatalogInterface {

  /**
   * The sections a person may add to this page.
   *
   * Parents only: a slide belongs inside a slideshow, not on a page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The page.
   * @param string $field
   *   The paragraph field the sections would go into.
   *
   * @return array<string, string>
   *   Bundle machine name => human label, sorted by label.
   */
  public function addable(NodeInterface $node, string $field): array;

  /**
   * The sections already on this page, top level only.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The page.
   * @param string $field
   *   The paragraph field to read.
   *
   * @return list<array{id: int, bundle: string, label: string, title: string, dom_id: string, position: int}>
   *   One entry per section, in page order. `dom_id` is the id PB already
   *   renders on the section, which is also what the hover note shows.
   */
  public function onPage(NodeInterface $node, string $field): array;

}
