# PDF Generator

Schoolbox is able to make use of a PDF generator, in order to convert HTML pages
to downloadable PDFs. (Schoolbox uses [Gotenberg](https://gotenberg.dev/)
as its PDF generator.)

Usually, the generator will be accessed via the [task daemon](task-daemon.md):
however, it is possible for Schoolbox to access the generator directly via its
[adapter](../adapters/pdf-generator.md).

## Fonts

Gotenberg uses Chromium to render HTML pages to PDFs. The fonts available during
rendering come from two sources: system fonts installed inside the Gotenberg
container, and web fonts loaded by the page itself.

### System fonts

The `gotenberg/gotenberg:8` Docker image ships with a comprehensive set of system
fonts, covering Latin scripts and a broad range of international scripts.

**Common Western fonts (Microsoft core fonts):**

| Font family | Notes |
|---|---|
| Arial, Arial Black | |
| Courier New | |
| Comic Sans MS | |
| Georgia | |
| Impact | |
| Times New Roman | |
| Trebuchet MS | |
| Verdana | |
| Andale Mono | |
| Webdings | |

**Liberation fonts** (metric-compatible substitutes for Microsoft core fonts):

| Font family | Replaces |
|---|---|
| Liberation Sans | Arial |
| Liberation Serif | Times New Roman |
| Liberation Mono | Courier New |

**DejaVu fonts:**

| Font family |
|---|
| DejaVu Sans |
| DejaVu Serif |
| DejaVu Sans Mono |
| DejaVu Math TeX Gyre |

**Linux Libertine / Linux Biolinum:**

| Font family |
|---|
| Linux Libertine O (Regular, Bold, Italic, Bold Italic, Semibold, Display, Initials, Mono) |
| Linux Biolinum O (Regular, Bold, Italic) |
| Linux Biolinum Keyboard O |

**Caladea and Carlito** (metric-compatible with Cambria and Calibri):

| Font family | Replaces |
|---|---|
| Caladea | Cambria |
| Carlito | Calibri |

**CJK (Chinese, Japanese, Korean):**

| Font family | Script |
|---|---|
| Noto Sans CJK HK / JP / KR / SC / TC | Chinese/Japanese/Korean (sans-serif) |
| Noto Serif CJK HK / JP / KR / SC / TC | Chinese/Japanese/Korean (serif) |
| Noto Sans Mono CJK HK / JP / KR / SC / TC | Chinese/Japanese/Korean (monospace) |
| IPA Gothic, IPA P Gothic | Japanese |
| IPA Mincho, IPA P Mincho | Japanese |
| AR PL UKai CN / HK / TW / TW MBE | Chinese (kaiti/calligraphic) |
| AR PL UMing CN / HK / TW / TW MBE | Chinese (mingti/serif) |
| WenQuanYi Zen Hei | Chinese |
| UnBatang, UnDinaru, UnDotum, UnGraphic, UnGungseo, UnPilgi | Korean |

**Arabic script:**

| Font family |
|---|
| Amiri, Amiri Quran, Amiri Quran Colored |
| Noto Naskh Arabic, Noto Kufi Arabic, Noto Nastaliq Urdu, Noto Rashi Hebrew |

**Hebrew:**

| Font family |
|---|
| Aharoni CLM, David CLM, Ellinia CLM, Frank Ruehl CLM, Hadasim CLM, Miriam CLM, Miriam Mono CLM, Nachlieli CLM, Shofar, Simple CLM, Stam Ashkenaz CLM, Stam Sefarad CLM, Yehuda CLM |

**Indic scripts:**

| Font family | Script |
|---|---|
| Lohit Bengali, Lohit Assamese | Bengali/Assamese |
| Lohit Gurmukhi | Punjabi |
| Lohit Kannada | Kannada |
| Lohit Telugu | Telugu |
| Sarai | Hindi/Devanagari |
| Various Noto Sans/Serif Indic variants | Devanagari, Bengali, Gujarati, Gurmukhi, Tamil, Telugu, Kannada, Malayalam, Oriya, Sinhala, etc. |

**Thai:**

| Font family |
|---|
| Garuda, Kinnari, Laksaman, Loma, Norasi, Purisa, Sawasdee, Tlwg Mono, Tlwg Typewriter, Tlwg Typist, Tlwg Typo, Umpush, Waree |

**Other:**

| Font family | Notes |
|---|---|
| Noto Color Emoji | Colour emoji |
| Noto Sans, Noto Serif | Broad script coverage across hundreds of writing systems |
| Noto Mono | Monospace |
| Abyssinica SIL | Ethiopic |
| Gentium, Gentium Basic, Gentium Book Basic, GentiumAlt | Broad Latin/Greek/Cyrillic/IPA |
| Padauk, Padauk Book | Myanmar/Burmese |
| OpenSymbol | Symbols (from LibreOffice) |
| LKLUG | Sinhala |

### Web fonts

Because Gotenberg uses Chromium to render pages, it also supports web fonts
loaded via CSS `@font-face` rules — as long as the font files are accessible
from the Gotenberg container at render time.

Schoolbox itself loads several custom fonts this way:

| Font | Loaded from |
|---|---|
| `schoolbox` (icon font, legacy UI) | CDN (`/fonts/schoolbox.ttf`, `.woff`, `.svg`) |
| `sbx-icons` (icon font, advanced UI) | CDN (`/fonts/sbx-icons.ttf`, `.woff`, `.svg`) |
| `LibreBarcode39` (barcode font) | CDN (`/fonts/LibreBarcode39-Regular.ttf`) |

These fonts are served from the CDN prefix configured for the Schoolbox
instance. As long as the Gotenberg container has network access to that CDN
(which it does in both the development environment and in production), these
fonts will render correctly in generated PDFs.

### Adding custom fonts

If a custom font is needed for PDF generation that is not already installed in
the Gotenberg container, there are two options:

1. **Use a web font via CSS `@font-face`**: If the font file is hosted at a URL
   accessible from the Gotenberg container, it can be loaded with a
   `@font-face` rule in the page's CSS.

2. **Build a custom Gotenberg image**: A custom `Dockerfile` can be created
   based on `gotenberg/gotenberg:8`, which copies font files into
   `/usr/local/share/fonts/` and runs `fc-cache -f` to register them.
