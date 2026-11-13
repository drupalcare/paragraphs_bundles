#!/usr/bin/env python3
"""Static check of the Paragraphs Bundles Pattern rules that can be read from the files.

Reads modules/paragraphs_bundles_patterns. No site and no browser. Field names and setting values are checked by the
module's Kernel tests, which have the real configuration.

  R1  every selector sits under the Pattern's own root class.
  R2  the bundle's .pb-ui-inner is painted or padded only in a :where() rule, so the editor's settings win.
  R3  no !important outside reduced-motion and forced-colors.
  R4  no color literal in Pattern CSS (the shared base is the one home; masks read alpha only and are exempt).
  R5  no theme variable: every var(--x) is one PB or the Pattern declares; no --r-* or --apex-* outside css/bridge/.
  R6  logical properties only: no left, right, margin-left and the like, except PHYSICAL_OK with its reason.
  R9  pattern.yml states stage: open|boxed and ground: dark|light.
  R11 a file that animates has a prefers-reduced-motion block.
  R12 pattern.yml states serves: with 2 to 4 known kinds of site.
  R15 pattern.yml states placement: outer-margin|inner-padding|both|neither.
  R18 a size that scales with the screen or region has a floor or ceiling.
  R22 no font shorthand uses a family token: unset, it voids the whole line.
  R19 every Pattern has a sample in data/samples.json.
  R20 no emoji or exclamation mark in the words an editor reads.
  R21 a flip face that does not turn stays hidden until it is uncovered.

Exit 1 with path:line on a failure, and when nothing was checked.
Usage: check-pattern-rules.py [--root DIR] | --self-test
"""
import argparse, glob, json, os, re, sys, tempfile

HERE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
KINDS = set("associations corporate creative cultural events government healthcare nonprofit publication saas studio university".split())
COLOUR = re.compile(r"#[0-9a-fA-F]{3,8}\b|\b(?:rgba?|hsla?|oklch|oklab)\(")
MASK_PROPS = {"mask", "-webkit-mask", "mask-image", "-webkit-mask-image"}
PHYSICAL = set("left right margin-left margin-right padding-left padding-right border-left border-right border-left-width border-right-width border-left-color border-right-color".split())
ANIMATES = re.compile(r"(?:^|[;{\s])(?:transition|animation)(?:-[a-z-]+)?\s*:\s*(?!none)", re.M)
SCALING = re.compile(r"[\d.]\s*(?:vw|vh|svh|dvh|lvh|vmin|vmax|cqi|cqw|cqh)\b")
R18_PROPS = set("gap row-gap column-gap padding padding-block padding-inline margin margin-block margin-inline width height inline-size block-size flex-basis font-size top bottom inset inset-block inset-inline".split())
EMOJI = re.compile("[\U0001F300-\U0001FAFF☀-➿]")
DECL = re.compile(r"(?:(?<=[{;])|^)\s*(--[\w-]+|-?[a-z-]+)\s*:\s*([^;{}]+?)(?=\s*[;}])", re.M)

# (file name, property): why a physical property is right there.
PHYSICAL_OK = {}
# (file name, property): why a scaling size has no ceiling of its own.
R18_OK = {
    ("pb-pattern-grid-panels.css", "width"): "the open panel's exact share of its own row; the row is the ceiling",
    ("pb-pattern-image-overlay-spine.css", "width"): "the flat label is exactly the photo's width; the photo is the ceiling",
}
# bundle/name: why the Pattern has no save rule.
SAMPLES_FILE = os.path.join(HERE, "data", "samples.json")
SAMPLES = json.load(open(SAMPLES_FILE)) if os.path.exists(SAMPLES_FILE) else {}


def read(path):
    with open(path, encoding="utf-8") as fh:
        return fh.read()


def mask(text):
    return re.sub(r"/\*.*?\*/", lambda m: re.sub(r"[^\n]", " ", m.group(0)), text, flags=re.S)


def line_of(text, pos):
    return text.count("\n", 0, pos) + 1


def spans(text, pattern):
    out = []
    for m in re.finditer(pattern, text):
        depth, i = 1, m.end()
        while i < len(text) and depth:
            depth += {"{": 1, "}": -1}.get(text[i], 0)
            i += 1
        out.append((m.start(), i))
    return out


def split_selectors(head):
    """Splits a selector list on its top-level commas only."""
    parts, depth, cur = [], 0, ""
    for ch in head:
        depth += {"(": 1, ")": -1}.get(ch, 0)
        if ch == "," and depth == 0:
            parts.append(cur)
            cur = ""
        else:
            cur += ch
    return parts + [cur]


def known_vars(root):
    known = set()
    base = os.path.dirname(os.path.dirname(root))
    for path in glob.glob(os.path.join(base, "**", "*"), recursive=True):
        if f"{os.sep}tests{os.sep}" in path or "node_modules" in path or not path.endswith((".css", ".php", ".js", ".twig", ".module")):
            continue
        if f"{os.sep}bridge{os.sep}" in path:
            continue
        text = read(path)
        known |= set(re.findall(r"(--[\w-]+)\s*:", text))
        known |= set(re.findall(r"""['"`](--[\w-]+)['"`]""", text))
        known |= set(re.findall(r"(--pb-[\w-]+)", text) if path.endswith((".php", ".twig", ".module")) else [])
    return known


def rules_in(text):
    """Yields (selector text, selector position, body, body position) with at-rule prelude removed."""
    i, n = 0, len(text)
    stack_at = []
    buf_start = 0
    while i < n:
        ch = text[i]
        if ch == "{":
            head = text[buf_start:i].strip()
            if head.startswith("@"):
                stack_at.append(head)
                i += 1
                buf_start = i
                continue
            depth, j = 1, i + 1
            while j < n and depth:
                depth += {"{": 1, "}": -1}.get(text[j], 0)
                j += 1
            yield head, buf_start + (len(text[buf_start:i]) - len(text[buf_start:i].lstrip())), text[i + 1:j - 1], i + 1, list(stack_at)
            i = j
            buf_start = i
            continue
        if ch == "}":
            if stack_at:
                stack_at.pop()
            i += 1
            buf_start = i
            continue
        if ch == ";":
            head = text[buf_start:i].strip()
            if head.startswith("@"):
                i += 1
                buf_start = i
                continue
        i += 1


def check_css(path, root, known, out):
    text = mask(read(path))
    rel = os.path.relpath(path, root)
    name = os.path.basename(path)
    bundle = rel.split(os.sep)[1]
    pattern = rel.split(os.sep)[2]
    root_class = "pb-pattern--" + re.sub(r"_bundle$", "", bundle).replace("_", "-") + "-" + pattern.replace("_", "-")
    calm = spans(text, r"@media[^{]*(?:prefers-reduced-motion|forced-colors)[^{]*\{")
    keyframes = spans(text, r"@(?:-webkit-)?keyframes[^{]*\{")
    for head, pos, body, body_pos, ats in rules_in(text):
        if any("keyframes" in a for a in ats):
            continue
        for sel in split_selectors(head):
            if root_class not in sel:
                out.append(f"R1 {rel}:{line_of(text, pos)} selector is not under .{root_class}: {sel.strip()[:70]}")
        if "pb-ui-inner" in head:
            for sel in split_selectors(head):
                own_inner = re.search(re.escape(root_class) + r"\s*>?\s*\.pb-ui-inner\s*$", sel.strip())
                if own_inner and not sel.strip().startswith(":where("):
                    if re.search(r"(?:^|;|\s)(padding|background|color|border)[a-z-]*\s*:", body):
                        out.append(f"R2 {rel}:{line_of(text, pos)} paints .pb-ui-inner outside :where()")
    for m in DECL.finditer(text):
        prop, value = m.group(1), m.group(2)
        at = f"{rel}:{line_of(text, m.start(1))}"
        in_calm = any(a <= m.start() < b for a, b in calm)
        if any(a <= m.start() < b for a, b in keyframes) and prop.startswith("--") is False and not COLOUR.search(value):
            continue
        if "!important" in value and not in_calm:
            out.append(f"R3 {at} !important on {prop}")
        bare = re.sub(r"url\([^)]*\)", "", value)
        if prop not in MASK_PROPS and COLOUR.search(bare):
            out.append(f"R4 {at} color literal in {prop}: {value[:60]}")
        for var in re.findall(r"var\(\s*(--[\w-]+)", bare):
            if var.startswith(("--r-", "--apex-")):
                out.append(f"R5 {at} theme variable {var}")
            elif var not in known:
                out.append(f"R5 {at} var({var}) is not declared anywhere")
        if prop == "font" and "--pb-pattern-font-" in bare:
            out.append(f"R22 {at} font shorthand with an unset family token loses its whole declaration")
        if prop in PHYSICAL and (name, prop) not in PHYSICAL_OK and bare.strip() != "auto":
            out.append(f"R6 {at} physical property {prop}")
        if prop in R18_PROPS and SCALING.search(bare) and (name, prop) not in R18_OK:
            if not re.search(r"\b(?:clamp|min|max)\(", bare):
                out.append(f"R18 {at} {prop} scales with no floor or ceiling: {value[:60]}")
    if re.search(r"__back\s*\{[^}]*transform:\s*(?:none|translateZ)", text) and not re.search(r"__back\s*\{[^}]*opacity:\s*0\s*;", text):
        out.append(f"R21 {rel} back face does not turn and is not hidden, so it shows through the front")
    if ANIMATES.search(text) and "prefers-reduced-motion" not in text:
        out.append(f"R11 {rel} moves but has no prefers-reduced-motion block")


def yml_value(text, key):
    m = re.search(r"^%s:[ \t]*(.*)$" % key, text, re.M)
    return m.group(1).strip().strip("'\"") if m else None


def check_yml(path, root, out):
    text = read(path)
    rel = os.path.relpath(path, root)
    key = "/".join(rel.split(os.sep)[1:3])
    if yml_value(text, "stage") not in ("open", "boxed"):
        out.append(f"R9 {rel} needs stage: open or boxed")
    if yml_value(text, "ground") not in ("dark", "light"):
        out.append(f"R9 {rel} needs ground: dark or light")
    if yml_value(text, "placement") not in ("outer-margin", "inner-padding", "both", "neither"):
        out.append(f"R15 {rel} needs placement: outer-margin, inner-padding, both or neither")
    serves = yml_value(text, "serves")
    tags = [t.strip() for t in re.sub(r"[\[\]]", "", serves or "").split(",") if t.strip()]
    if not 2 <= len(tags) <= 4 or set(tags) - KINDS:
        out.append(f"R12 {rel} needs serves: 2 to 4 known kinds of site, got {serves!r}")
    if key.replace("/", ".") not in SAMPLES:
        out.append(f"R19 {rel} has no sample in data/samples.json")
    words = " ".join(re.findall(r"^(?:label|description):\s*(.*)$|^  - (.*)$", text, re.M).__str__())
    if EMOJI.search(words) or re.search(r"[a-z]!", words):
        out.append(f"R20 {rel} emoji or exclamation mark in editor words")


def run(root):
    out = []
    known = known_vars(root)
    css = sorted(glob.glob(os.path.join(root, "patterns", "*", "*", "*.css")))
    yml = sorted(glob.glob(os.path.join(root, "patterns", "*", "*", "pattern.yml")))
    for path in css:
        check_css(path, root, known, out)
    for path in yml:
        check_yml(path, root, out)
    for path in glob.glob(os.path.join(root, "css", "bridge", "*.css")):
        if COLOUR.search(re.sub(r"var\([^()]*,\s*#[0-9a-f]+\)", "", mask(read(path)))) and "oklch(from" not in read(path):
            out.append(f"R4 {os.path.relpath(path, root)} color literal in a bridge")
    return out, len(css), len(yml)


def self_test():
    cases = [
        ("a { color: red; }", "R1"),
        (".pb-pattern--x-y > .pb-ui-inner { padding: 1px; }", "R2"),
        (".pb-pattern--x-y a { margin: 0 !important; }", "R3"),
        (".pb-pattern--x-y a { color: #123456; }", "R4"),
        (".pb-pattern--x-y a { color: var(--r-bg); }", "R5"),
        (".pb-pattern--x-y a { color: var(--pb-not-a-real-one); }", "R5"),
        (".pb-pattern--x-y a { margin-left: 1px; }", "R6"),
        (".pb-pattern--x-y a { transition: opacity 1s; }", "R11"),
        (".pb-pattern--x-y a { width: 50vw; }", "R18"),
        (".pb-pattern--x-y .f__back { transform: none; }", "R21"),
        (".pb-pattern--x-y a { font: 700 1rem / 1 var(--pb-pattern-font-display); }", "R22"),
    ]
    good = ":where(.pb-pattern--x-y > .pb-ui-inner) { padding: 1px; }\n.pb-pattern--x-y a { width: min(50vw, 9rem); margin-inline-start: 1px; }\n"
    ok = True
    with tempfile.TemporaryDirectory() as tmp:
        root = os.path.join(tmp, "modules", "paragraphs_bundles_patterns")
        path = os.path.join(root, "patterns", "x_bundle", "y", "pb-pattern-x-y.css")
        os.makedirs(os.path.dirname(path))
        for css, rule in cases + [(good, None)]:
            with open(path, "w", encoding="utf-8") as fh:
                fh.write(css)
            out = []
            check_css(path, root, known_vars(root), out)
            hit = [o for o in out if o.startswith(rule + " ")] if rule else out
            passed = bool(hit) if rule else not out
            print(("ok  " if passed else "FAIL"), rule or "clean", css[:50].replace("\n", " "), "" if passed else out)
            ok = ok and passed
        yml = os.path.join(os.path.dirname(path), "pattern.yml")
        with open(yml, "w", encoding="utf-8") as fh:
            fh.write("label: 'Y'\ndescription: 'Y'\nrequires: {}\n")
        out = []
        check_yml(yml, root, out)
        got = {o.split()[0] for o in out}
        passed = {"R9", "R12", "R15", "R19"} <= got
        print(("ok  " if passed else "FAIL"), "yml", sorted(got))
        ok = ok and passed
    return 0 if ok else 1


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--root", default=HERE)
    ap.add_argument("--self-test", action="store_true")
    args = ap.parse_args()
    if args.self_test:
        return self_test()
    out, css, yml = run(args.root)
    for line in out:
        print(line)
    print(f"checked {css} CSS files and {yml} pattern.yml: {len(out)} failures")
    return 1 if out or not (css and yml) else 0


if __name__ == "__main__":
    sys.exit(main())
