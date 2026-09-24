<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraph_bundle_icon\Unit;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\paragraph_bundle_icon\Hook\PbiEntityHook;
use Drupal\paragraphs\ParagraphInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The icon subset is rebuilt when, and only when, the icons in use change.
 *
 * WHY THIS MATTERS. The subset URL is baked into a library definition and
 * library definitions are cached. Without invalidation an author who adds an
 * icon gets a font that does not contain it — the icon renders as its ligature
 * NAME in plain text until some unrelated cache clear happens to rebuild the
 * registry. That is the failure that forced the revert in 4ea0ce5, arriving by
 * a slower route.
 *
 * ⚠ THIS TEST INTRODUCES A CONVENTION THIS PROJECT HAS NOT USED BEFORE: no
 * other Unit test here sets up a container. `Cache::invalidateTags()` is static
 * and resolves `cache_tags.invalidator` through `\Drupal`, so the class cannot
 * be tested at all without one. The alternative was to leave the only piece of
 * this feature whose job is cache correctness with no harness, which seemed the
 * worse trade. Flagged rather than slipped in.
 *
 * 🚨 THE "UNCHANGED" BRANCH WAS UNTESTED UNTIL 2026-09-23, AND IT IS THE WHOLE
 * POINT OF THE COMPARISON. Every test here asserted that something DOES
 * invalidate; nothing asserted that re-saving an icon whose name and style are
 * unchanged does NOT. So the early-return could have been deleted outright and
 * the suite would have stayed green while every content edit rebuilt the
 * library registry. ::testUnchangedIconDoesNotInvalidate closes that.
 *
 * @group paragraphs_bundles
 */
#[Group('paragraphs_bundles')]
final class PbiEntityHookTest extends TestCase {

  /**
   * The icon name field the hook compares.
   */
  private const NAME_FIELD = 'pb_content_gms_icon_name';

  /**
   * The style field the hook compares.
   */
  private const STYLE_FIELD = 'pb_content_gms_style';

  /**
   * The invalidator the hook reaches through \Drupal.
   */
  private MockObject $invalidator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->invalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $container = new ContainerBuilder();
    $container->set('cache_tags.invalidator', $this->invalidator);
    \Drupal::setContainer($container);
  }

  /**
   * A paragraph of a given bundle, with no field values readable.
   *
   * @param string $bundle
   *   Paragraph bundle.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   Mock paragraph.
   */
  private function paragraph(string $bundle): ParagraphInterface {
    $paragraph = $this->createMock(ParagraphInterface::class);
    $paragraph->method('bundle')->willReturn($bundle);
    $paragraph->method('hasField')->willReturn(FALSE);

    return $paragraph;
  }

  /**
   * An icon paragraph carrying a readable name and style.
   *
   * @param string $name
   *   Icon name.
   * @param string $style
   *   Icon style.
   * @param \Drupal\paragraphs\ParagraphInterface|null $original
   *   What getOriginal() should return, or NULL for none.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   Mock paragraph.
   */
  private function icon(string $name, string $style, ?ParagraphInterface $original = NULL): ParagraphInterface {
    $paragraph = $this->createMock(ParagraphInterface::class);
    $paragraph->method('bundle')->willReturn('icon_bundle');
    $paragraph->method('hasField')->willReturn(TRUE);
    $paragraph->method('getOriginal')->willReturn($original);
    $paragraph->method('get')->willReturnCallback(
      function (string $field) use ($name, $style): FieldItemListInterface {
        $value = $field === self::NAME_FIELD ? $name : ($field === self::STYLE_FIELD ? $style : '');

        $item = $this->createMock(FieldItemInterface::class);
        $item->method('getString')->willReturn($value);

        $list = $this->createMock(FieldItemListInterface::class);
        $list->method('isEmpty')->willReturn($value === '');
        $list->method('first')->willReturn($item);

        return $list;
      },
    );

    return $paragraph;
  }

  /**
   * A non-paragraph entity, as other modules' hooks would pass.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Mock entity.
   */
  private function otherEntity(): EntityInterface {
    return $this->createMock(EntityInterface::class);
  }

  /**
   * Adding an icon paragraph rebuilds the subset.
   */
  public function testInsertingAnIconInvalidatesTheLibraryCache(): void {
    $this->invalidator->expects($this->once())
      ->method('invalidateTags')
      ->with(['library_info']);

    (new PbiEntityHook())->paragraphInsert($this->paragraph('icon_bundle'));
  }

  /**
   * Deleting an icon paragraph rebuilds the subset.
   *
   * The deleted icon may have been the only user of that glyph, so the subset
   * must shrink; otherwise every page keeps paying for an icon nobody shows.
   */
  public function testDeletingAnIconInvalidatesTheLibraryCache(): void {
    $this->invalidator->expects($this->once())
      ->method('invalidateTags')
      ->with(['library_info']);

    (new PbiEntityHook())->paragraphDelete($this->paragraph('icon_bundle'));
  }

  /**
   * Another bundle's paragraph never touches the icon subset.
   *
   * Every paragraph save on the site runs through these hooks. Invalidating
   * `library_info` on all of them would rebuild the whole library registry on
   * every content edit.
   */
  public function testAnotherBundleNeverInvalidates(): void {
    $this->invalidator->expects($this->never())->method('invalidateTags');

    $hook = new PbiEntityHook();
    $hook->paragraphInsert($this->paragraph('simple_bundle'));
    $hook->paragraphUpdate($this->paragraph('simple_bundle'));
    $hook->paragraphDelete($this->paragraph('simple_bundle'));
  }

  /**
   * A non-paragraph entity is ignored rather than fataled on.
   */
  public function testNonParagraphEntitiesAreIgnored(): void {
    $this->invalidator->expects($this->never())->method('invalidateTags');

    $hook = new PbiEntityHook();
    $hook->paragraphInsert($this->otherEntity());
    $hook->paragraphUpdate($this->otherEntity());
    $hook->paragraphDelete($this->otherEntity());
  }

  /**
   * Updating an icon with no pre-save state still rebuilds.
   *
   * `getOriginal()` returns NULL on a fresh mock, and the hook cannot compare
   * against nothing — so it must fail SAFE and invalidate. Rebuilding once too
   * often costs a library-cache build; skipping one costs a missing icon.
   */
  public function testUpdatingAnIconWithoutOriginalInvalidates(): void {
    $this->invalidator->expects($this->once())
      ->method('invalidateTags')
      ->with(['library_info']);

    (new PbiEntityHook())->paragraphUpdate($this->paragraph('icon_bundle'));
  }

  /**
   * Re-saving an icon whose name and style are unchanged rebuilds NOTHING.
   *
   * 🚨 THE BRANCH THIS COVERS HAD NO TEST AT ALL. Deleting the early return
   * would have left every assertion in this class green while turning every
   * edit of an icon paragraph — a colour tweak, a title change — into a full
   * library-registry rebuild.
   */
  public function testUnchangedIconDoesNotInvalidate(): void {
    $this->invalidator->expects($this->never())->method('invalidateTags');

    $original = $this->icon('star', 'outlined');
    $entity = $this->icon('star', 'outlined', $original);

    (new PbiEntityHook())->paragraphUpdate($entity);
  }

  /**
   * Changing the icon NAME rebuilds the subset.
   *
   * The control for the test above: without this, an implementation that never
   * invalidated on update would pass it.
   */
  public function testChangingTheIconNameInvalidates(): void {
    $this->invalidator->expects($this->once())
      ->method('invalidateTags')
      ->with(['library_info']);

    $original = $this->icon('star', 'outlined');
    $entity = $this->icon('rocket_launch', 'outlined', $original);

    (new PbiEntityHook())->paragraphUpdate($entity);
  }

  /**
   * Changing the STYLE rebuilds the subset too.
   *
   * A style change asks Google for a different font family, so the same glyph
   * name still needs a new request.
   */
  public function testChangingTheIconStyleInvalidates(): void {
    $this->invalidator->expects($this->once())
      ->method('invalidateTags')
      ->with(['library_info']);

    $original = $this->icon('star', 'outlined');
    $entity = $this->icon('star', 'sharp', $original);

    (new PbiEntityHook())->paragraphUpdate($entity);
  }

}
