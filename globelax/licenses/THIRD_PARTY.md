# Third-party licenses — Globe.Lax

Globe.Lax itself is released under the **MIT License** (`../LICENSE`, © 2025 mohamadreza.lak).

A production Android build bundles the following third-party components. All of them are
permissive licenses that allow closed and open redistribution alike. The full texts are
included below so the app can be audited (and so the notices required by MIT/BSD survive
R8 minification).

| Component | Version | License | Used for |
|---|---|---|---|
| React / React DOM | 19.2.6 | MIT | UI runtime |
| Vite | 7.3.2 | MIT | build tool (not shipped) |
| Tailwind CSS | 4.1.17 | MIT | styling utilities |
| Lucide icons | 1.70.0 | ISC | UI iconography |
| d3-geo | 3.1.2 | ISC | orthographic map projection |
| topojson-client | 3.1.0 | ISC | topology decoding |
| world-atlas (Natural Earth) | 2.0.2 | Public domain (Natural Earth) / ISC wrapper | country outlines |
| country-flag-icons | 1.6.20 | MIT | offline SVG flags |
| i18n-iso-countries | 7.14.0 | MIT | ISO 3166 numeric ↔ alpha-2 |
| Vazirmatn font | 33.x (variable) | SIL Open Font License 1.1 | Persian typography |
| Capacitor (core, cli, android, app, status-bar, splash-screen) | 7.x | MIT | native Android shell |
| AndroidX (appcompat, core, coordinatorlayout, splashscreen, webkit) | see `android/variables.gradle` | Apache License 2.0 | Android runtime |
| JetBrains Kotlin stdlib / coroutines | see Gradle graph | Apache License 2.0 | runtime |

---

## MIT License

```
MIT License

Copyright (c) the respective authors

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

## ISC License (Lucide, d3-geo, topojson-client)

```
ISC License

Copyright (c) the respective authors

Permission to use, copy, modify, and/or distribute this software for any
purpose with or without fee is hereby granted, provided that the above
copyright notice and this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
```

## SIL Open Font License 1.1 (Vazirmatn)

```
Copyright (c) 2015-2025 The Vazirmatn Project Authors (https://github.com/rastikerdar/vazirmatn)

This Font Software is licensed under the SIL Open Font License, Version 1.1.
This license is available with a FAQ at: https://openfontlicense.org

PERMISSION & CONDITIONS
Permission is hereby granted, free of charge, to any person obtaining a copy of the
Font Software, to use, study, copy, merge, embed, modify, redistribute, and sell
modified and unmodified copies of the Font Software, subject to the following
conditions:

1) Neither the Font Software nor any of its individual components, in Original or
   Modified Versions, may be sold by itself.
2) Original or Modified Versions of the Font Software may be bundled, redistributed
   and/or sold with any software, provided that each copy contains the above
   copyright notice and this license. These can be included either as stand-alone
   text files, human-readable headers or in the appropriate machine-readable
   metadata fields within text or binary files as long as those fields can be
   easily viewed by the user.
3) No Modified Version of the Font Software may use the Reserved Font Name(s)
   unless explicit written permission is granted by the corresponding Copyright
   Holder.
4) The name(s) of the Copyright Holder(s) or the Author(s) of the Font Software
   shall not be used to promote, endorse or advertise any Modified Version, except
   to acknowledge the contribution(s) of the Copyright Holder(s) and the Author(s)
   or with their explicit written permission.
5) The Font Software, modified or unmodified, in part or in whole, must be
   distributed entirely under this license, and must not be distributed under any
   other license.

THE FONT SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO ANY WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT OF COPYRIGHT, PATENT, TRADEMARK, OR
OTHER RIGHT.
```

## Apache License 2.0 (AndroidX, Kotlin)

The full Apache License 2.0 text is available at
<https://www.apache.org/licenses/LICENSE-2.0>. AndroidX artifacts and the Kotlin
standard library ship under this license; no modifications were made to them.

## Natural Earth (map geometry)

Natural Earth data is in the **public domain** (<https://www.naturalearthdata.com/about/terms-of-use/>).
The `world-atlas` npm package that redistributes it is ISC-licensed.

## Country data

Facts (capital, currency, area, population, languages, continent) are compiled from
public reference sources (UN Statistics Division, World Bank Open Data, CIA World
Factbook, ISO 3166-1, ISO 4217) and are **not** copyrightable as individual facts.
See `../README.md` for the exact figures, years and caveats.
