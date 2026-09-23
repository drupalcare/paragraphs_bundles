<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_icon\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraph_bundle_icon\Service\PbiFontUrlBuilderInterface;
use Drupal\paragraphs_bundles\Utility\PbRenderArray;

/**
 * Declares the font host a subsetted icon request needs.
 *
 * WHY THIS EXISTS, AND WHY ONLY font-src. The Content-Security-Policy module
 * builds policy sources from library definitions automatically, so putting the
 * subset URL inside a library already covers the stylesheet. Measured by
 * reading `LibraryPolicyBuilder` directly: its css branch appends the external
 * host to `style-src` and `style-src-elem`, and the string `font-src` does not
 * appear anywhere in that file.
 *
 * That leaves exactly one gap. The stylesheet is fetched from
 * fonts.googleapis.com — now allowed — and it then references the font file on
 * fonts.gstatic.com, which nothing allows. On a site enforcing a strict policy
 * the CSS would load and the font would be blocked, so every icon would render
 * as a blank box. Worse than the payload problem it replaces, and invisible on
 * any site not running a strict policy.
 *
 * `#attached['csp']` is the per-element route the module documents for modules
 * (`hook_csp_policy_alter()` is themes only). RenderElementAttachedCspSubscriber
 * iterates the attachment by directive name, so `font-src` is carried through
 * like any other. When the csp module is absent the key is simply an unread
 * entry in `#attached` — which is why this adds no dependency, per the rule in
 * docs/CSP.md: every csp integration degrades to nothing when it is not there.
 */
final class PbiPreprocessIconHook {

  /**
   * Implements hook_preprocess_paragraph__icon_bundle().
   *
   * @param array<string, mixed> $variables
   *   Render variables (passed by reference).
   */
  #[Hook('preprocess_paragraph__icon_bundle')]
  public function preprocessIconBundle(array &$variables): void {
    foreach (PbiFontUrlBuilderInterface::CSP_SOURCES as $directive => $sources) {
      PbRenderArray::append($variables, ['#attached', 'csp', $directive], ...$sources);
    }
  }

}
