<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\paragraphs_bundles\Hook\PbParagraphEditHook;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * The save-back-to-list redirect applies only on the list's own edit route.
 *
 * Applying it to every paragraph form sent editors on a node form to a list
 * they had not asked for.
 */
#[Group('paragraphs_bundles')]
final class PbParagraphEditHookTest extends UnitTestCase {

  /**
   * Builds the hook with a route match answering the given route name.
   */
  private function hook(?string $routeName): PbParagraphEditHook {
    $routeMatch = $this->createStub(RouteMatchInterface::class);
    $routeMatch->method('getRouteName')->willReturn($routeName);
    return new PbParagraphEditHook($routeMatch);
  }

  /**
   * Runs the alter on a form with a bare submit button.
   *
   * @return list<mixed>
   *   The submit handlers afterwards.
   */
  private function alter(PbParagraphEditHook $hook): array {
    $form = ['actions' => ['submit' => ['#submit' => ['::submitForm']]]];
    $form_state = $this->createStub(FormStateInterface::class);
    $hook->formParagraphFormAlter($form, $form_state, 'paragraph_form');
    return $form['actions']['submit']['#submit'];
  }

  /**
   * On the list's edit route, the redirect handler is added.
   */
  public function testAddedOnTheEditRoute(): void {
    $submit = $this->alter($this->hook('paragraphs_bundles.paragraph_edit'));
    $this->assertCount(2, $submit);
    $this->assertSame([PbParagraphEditHook::class, 'redirectToList'], $submit[1]);
  }

  /**
   * Anywhere else — a node form, a layout, no route — the form is untouched.
   */
  public function testNotAddedOnAnyOtherRoute(): void {
    foreach (['entity.node.edit_form', 'pb_import.paragraph_edit', '', NULL] as $route) {
      $this->assertSame(['::submitForm'], $this->alter($this->hook($route)), (string) $route);
    }
  }

}
