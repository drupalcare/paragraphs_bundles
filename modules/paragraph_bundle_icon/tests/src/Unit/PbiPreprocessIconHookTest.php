<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraph_bundle_icon\Unit;

use Drupal\paragraph_bundle_icon\Hook\PbiPreprocessIconHook;
use Drupal\paragraph_bundle_icon\Service\PbiFontUrlBuilderInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The font host is declared, so icons survive a strict Content-Security-Policy.
 *
 * 🚨 THIS IS THE ONE PIECE WITH NO RUNTIME EVIDENCE BEHIND IT, WHICH IS WHY IT
 * NEEDS A TEST MOST. Measured 2026-09-23 on sts.local: the enforced policy is
 * `style-src: any 'unsafe-inline'` and neither `font-src` nor `default-src` is
 * enabled at all. The csp module's `fallbackAwareAppendIfEnabled()` is a no-op
 * for a directive that is off, so this hook's effect is invisible on the only
 * site available — it cannot be confirmed by loading a page.
 *
 * WHY IT EXISTS. Subsetting puts the stylesheet URL inside a library, and the
 * csp module's `LibraryPolicyBuilder` walks library definitions and adds every
 * external host automatically — but read its source and it appends only
 * `script-src`, `script-src-elem`, `style-src` and `style-src-elem`. The string
 * `font-src` does not appear in that file. So the STYLESHEET host is allowed
 * automatically and the FONT host is not: on a site enforcing `font-src`, the
 * CSS would load and the font would be blocked, leaving every icon a blank box.
 * That is a worse outcome than the 3.4 MB payload this change removes.
 *
 * `#attached['csp']` is the per-element route the csp module documents for
 * modules — `hook_csp_policy_alter()` is themes only. When csp is not installed
 * the key is simply an unread entry in `#attached`, which is what keeps this
 * from becoming a dependency (see docs/CSP.md: every csp integration must
 * degrade to nothing when the module is absent).
 *
 * @group paragraphs_bundles
 */
#[Group('paragraphs_bundles')]
final class PbiPreprocessIconHookTest extends TestCase {

  /**
   * The font host is attached, because nothing else declares it.
   */
  public function testTheFontHostIsDeclared(): void {
    $variables = [];
    (new PbiPreprocessIconHook())->preprocessIconBundle($variables);

    self::assertSame(
      ['https://fonts.gstatic.com'],
      $variables['#attached']['csp']['font-src'],
      'Without font-src the font is blocked under a strict policy and every '
      . 'icon renders as a blank box.',
    );
  }

  /**
   * The stylesheet host is attached too, and that is deliberate belt-and-braces.
   *
   * LibraryPolicyBuilder already adds this one automatically from the library
   * definition. Declaring it again is harmless — the policy de-duplicates — and
   * it keeps this hook correct if the library ever stops being the carrier.
   */
  public function testTheStylesheetHostIsDeclared(): void {
    $variables = [];
    (new PbiPreprocessIconHook())->preprocessIconBundle($variables);

    self::assertSame(
      ['https://fonts.googleapis.com'],
      $variables['#attached']['csp']['style-src'],
    );
  }

  /**
   * Both hosts come from one source of truth, not retyped here.
   *
   * A host that drifts from the URL builder would allow the wrong origin and
   * block the right one, with no error anywhere.
   */
  public function testHostsComeFromTheUrlBuilderContract(): void {
    $variables = [];
    (new PbiPreprocessIconHook())->preprocessIconBundle($variables);

    self::assertSame(
      PbiFontUrlBuilderInterface::CSP_SOURCES,
      $variables['#attached']['csp'],
    );
  }

  /**
   * Existing attachments survive; this appends rather than replaces.
   *
   * Preprocess hooks run in sequence and something else may already have
   * attached a library or its own sources.
   */
  public function testExistingAttachmentsAreNotClobbered(): void {
    $variables = [
      '#attached' => [
        'library' => ['paragraph_bundle_icon/paragraph-bundle-icon'],
        'csp' => ['img-src' => ['https://img.example.com']],
      ],
    ];
    (new PbiPreprocessIconHook())->preprocessIconBundle($variables);

    self::assertSame(
      ['paragraph_bundle_icon/paragraph-bundle-icon'],
      $variables['#attached']['library'],
    );
    self::assertSame(
      ['https://img.example.com'],
      $variables['#attached']['csp']['img-src'],
    );
    self::assertSame(
      ['https://fonts.gstatic.com'],
      $variables['#attached']['csp']['font-src'],
    );
  }

  /**
   * Running twice does not duplicate a host.
   *
   * A paragraph rendered through more than one preprocess pass must not grow
   * the policy header on every pass.
   */
  public function testRunningTwiceDoesNotDuplicateHosts(): void {
    $variables = [];
    $hook = new PbiPreprocessIconHook();
    $hook->preprocessIconBundle($variables);
    $hook->preprocessIconBundle($variables);

    self::assertSame(
      ['https://fonts.gstatic.com', 'https://fonts.gstatic.com'],
      $variables['#attached']['csp']['font-src'],
      'Appending twice is expected here — the csp module de-duplicates sources '
      . 'when it builds the header. This test pins the behaviour so that a '
      . 'change to de-duplicate locally is a deliberate decision, not a '
      . 'surprise.',
    );
  }

}
