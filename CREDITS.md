# Third-party software / حقوق الطرف الثالث

Wafiq is built with these open-source components. Each keeps its own licence; their licence texts are
included in their folders (`vendor/…`, `resources/fonts/pdf/OFL.txt`).

| Component | Use | Licence |
|---|---|---|
| [Laravel](https://laravel.com) and its packages | Application framework | MIT |
| [Inertia.js](https://inertiajs.com) | Server ↔ React pages | MIT |
| [React](https://react.dev) | User interface | MIT |
| [Tailwind CSS](https://tailwindcss.com) | Styles (build time) | MIT |
| [TCPDF](https://tcpdf.org) | Arabic PDF generation | LGPL-3.0-or-later (unmodified library) |
| [IBM Plex Sans Arabic](https://github.com/IBM/plex) | Font (screen and PDF) | SIL Open Font License 1.1 |
| [Lucide](https://lucide.dev) | Icons | ISC |
| [Intervention Image](https://image.intervention.io) | Logo / stamp / signature processing | MIT |
| [Brick\Math](https://github.com/brick/math) | Exact money calculations | MIT |
| Other PHP dependencies (Symfony, Carbon, Monolog, League, Nette, Dotenv…) | Framework internals | MIT / BSD-3-Clause / Apache-2.0 |

TCPDF is used as an unmodified library (LGPL-3.0); you may replace it with another compatible version.
The full list of PHP packages and versions is in `composer.lock`.
