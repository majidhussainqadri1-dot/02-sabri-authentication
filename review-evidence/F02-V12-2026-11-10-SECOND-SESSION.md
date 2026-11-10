# فائل 02 — ماسٹر پلان v12 کی دوسری جامع اصلاحی نشست کی فنی و قبولیتی رپورٹ
**مورخہ:** 10 نومبر 2026  
**Repository:** `majidhussainqadri1-dot/02-sabri-authentication`  
**Draft PR:** #25 (PR #24 کے اوپر؛ PR #24 بدستور PR #23 کے اوپر)  
**ماخذ منصوبہ:** `Sabri-Website-Master-Plan-v12.docx`، پیراگراف 935–983، کل **48** منفرد شناختیں (12 FR + 10 NFR + 2 CEN + 24 X24)۔

## تمہیدِ حاکم
یہ ایک **Source/CI اصلاحی نشست** ہے؛ اس میں GitHub، Staging اور Live تین مستقل حقائق ہیں۔ کوڈنگ مکمل ہونے یا CI سبز ہونے کو عملی Provider، DB Migration، یا لائیو خرابی کا حل نہیں سمجھا جاسکتا۔ کسی لائیو واقعے کے لیے پہلے فعال پیکیج، ورژن، جدولیں، اختیارات، Migration State، Runtime Logs اور Checksums کی Live Reality Freeze لازمی ہے۔

## ثابت شدہ نقائص: پہلے ناکام، پھر اصلاح و تصدیق
| شمار | اصل سبب | Fail-first دلیل | اصلاح | موجودہ حد |
|---|---|---|---|---|
| R01 | `SAUTH_Password_Safety::check` میں معلوم Provider کی `WP_Error` یا ناقص جواب کو بعض صورتوں میں "صاف پاس ورڈ" سمجھنا | PHP 7.4/8.3، GitHub Actions #42787736032 دونوں ناکام؛ `RED: network/provider error falsely returned clean` | نا معتبر/نامکمل Response لازماً typed error؛ صرف واقعی غیر فعال اختیاری Provider پر Local-only fallback؛ 5-hex SHA-1 range سے اختیاری HIBP Adapter، محفوظ TLS، صفر Redirects، Padding، Limited bytes | حقیقی بیرونی API اور تعمیری دستیابی ابھی غیر مصدقہ |
| R02 | DPoP میں دستخط کی تصدیق **سے پہلے** مستقل Replay Claim بننا، جسے غیر مصدقہ درخواست غلط طور پر ضبط کر سکتی تھی | اسی fail-first run میں `RED: unverified DPoP signature owned durable replay claim before crypto proof` | پہلے غیر جارحانہ Replay Read، پھر Approved Signature Verification، آخر میں Atomic Once-only Claim؛ غلط دستخط پر کوئی مستقل Claim نہیں | اصل JOSE/Crypto Provider Signature Verifier ابھی موجود یا منظور شدہ ثابت نہیں |
| R03 | File 24 کے حقیقی `sauth/session_assurance` Consumer کے لیے File 02 کا مطلوبہ Read-only Projection غیر موجود | pinned File24 source کے ساتھ پہلے Job ناکام؛ `Call to undefined method SAUTH_Security_Orchestrator::file24_assurance_projection()` | موجودہ User، Session، Passkey Freshness، Risk اور Lockdown سے مربوط محدود Projection؛ غیر حساس `sauth:` HMAC حوالہ؛ دوسری شناخت، زیادہ Risk یا Lockdown پر تصدیقی دعویٰ منع | Source-level File24 Consumer Integration Tested؛ live owner acceptance باقی |
| R04 | File 25 کے Semantic Design Tokens کا File 02 پر Scoped Bridge موجود نہ ہونا | pinned File25 source پر پہلے `RED: File02 lacks scoped token bridge body.sabri-visual-system .sa-auth-shell` | اصل `--sabri-visual-*` tokens، fallback سبز، Dark Surfaces، Focus اور Forced Colors | حقیقی Browser، Urdu RTL، Zoom، Contrast اور Screenshot Acceptance باقی |
| R05 | بیرونی Owner/Provider Hook کی محض موجودگی کو حقیقی منظور شدہ سکیورٹی صلاحیت سمجھ لینے کا Operational خطرہ | Read-only release gate ٹیسٹ پہلے undefined method پر ناکام | System Check میں provider-by-provider `missing` یا `registered_unverified`، ہر صورت `acceptance:not_verified`؛ محفوظ Repair Block نہیں ہوتی | Provider/Founder approval نہ ہونے سے اکثریت ابھی ناقابل قبول |
| R06 | Google/Security/Password Recovery کے متعدد عوامی صفحات انگریزی سے غیر منتقل | اصل ماخذ میں سخت انگریزی متن | مزید سات Views پر ترجمانی، اردو و عربی عبارتیں اور `is_rtl()` سمت؛ مستقل Source Gate | مکمل Plugin Admin/operational strings، Browser QA باقی |

## موجودہ تقاضوں کی حالت
`review-evidence/F02-V12-2026-11-10-48-ACCEPTANCE-REGISTER.json` میں ہر ایک 48 ID کی الگ Source اور Release Acceptance State محفوظ ہے۔ **12/12 FR کے بنیادی Source implementations، 24/24 X24 میں implementation یا adapter path** موجود ہے؛ 10 NFR اور 2 CEN پر معیار و بین الشعبہ شواہد جزوی ہیں؛ **48/48 کی Production Acceptance غیر مصدقہ** ہے۔ یہ 48 ثابت شدہ خامیوں کا شمار نہیں۔

## سابق 13 Workstreams پر اس نشست کا اثر
1. **File00 Collision Owner:** حقیقی Authorized Owner اور Idempotent approval باقی؛ فرضی `allow` callback نہیں بنایا گیا۔
2. **File00 Recovery Owner:** Canonical Identity Evidence و Owner Acknowledgement باقی؛ Source پہلے سے one-time fail-closed ہے۔
3. **Recovery Reconciliation/Retention:** read-only Queue Monitor و typed uncertain owner errors موجود؛ Supervised Recovery، Legal Hold، Real Retention Drill باقی۔
4. **CAEP Provider:** Verification/Signature، Trusted issuer، Replay اور Live Session Enforcement قبولیت باقی۔
5. **RISC Provider:** Trusted signed source، Subject Mapping اور revocation acceptance باقی۔
6. **FedCM:** nonce/replay/fail-closed code موجود، حقیقی IdP verifier اور Browser acceptance باقی۔
7. **DPoP:** اس نشست میں Replay-before-signature نقص درست؛ approved Crypto Signature Verifier اور end-to-end token binding باقی۔
8. **FIDO:** `attestation=none` hardware guarantee نہیں؛ signed metadata provenance، revocation/trust/provider باقی۔
9. **File24:** pinned native Consumer Source Integration شامل اور CI green؛ staging/real consumer privileges و File00 owner authorization باقی۔
10. **File26:** pinned File26 contract میں File02 private connector مطلوب نہیں؛ No-Index/No-Store Source Boundary tested؛ Live Index Audit، current owner acceptance باقی۔
11. **File25:** pinned canonical tokens سے optional scoped design bridge اور forced-colors شامل؛ real a11y/UI, p75/p95 نہیں۔
12. **Localization:** Login/Signup/JS کے سابقہ اردو/عربی متون کے علاوہ Google, Access, Reset, Forgot, Profile Views ترجمہ پذیر؛ تمام Strings, Screen Reader, date/RTL acceptance باقی۔
13. **Password Breach:** مؤثر optional 5-hex HIBP adapter + outage fail-closed code موجود؛ activation configuration، approved vendor use/privacy decision، real provider drill باقی۔

## ثبوت اور Release کا ضابطہ
- Fail-first red reproducible on source before patch; subsequent exact-head PHP 7.4/8.3, WordPress 7.0.1/MariaDB 11.4 disposable CI, mock/structural consumer gates and deterministic package observed green at intermediate SHA. **آخری دستاویزی commit پر دوبارہ CI الگ دیکھنا ہوگا**؛ سابقہ سبز CI آخری commit کا ثبوت نہیں۔
- File24 pinned SHA `a5b8d49968a7a5a7d6f3f4655bea541bf38a9acb`; File25 pinned SHA `347a4ff4d4c233c5ea6cd82c7786ee5398ea9d1e`; File26 pinned SHA `1b064c6ea9f81195611ed001d5457e8ced65460a`; یہ ان کے Live Builds نہیں۔
- HIBP اختیاری: `SAUTH_HIBP_PASSWORD_CHECK_ENABLED` true تبھی جب Privacy/Legal/Operator approval اور Network Readiness جائزہ مکمل ہو؛ default پر کوئی upstream HTTP نہیں۔
- File00 PR55 pinned SHA `b3e9253d06a2897f092981f3a1f77e16b4b78578` کو CI میں استعمال کیا جاتا ہے۔ یہ File00 Live Parity نہیں۔
- تمام GitHub PRs Draft اور غیر ضم؛ کوئی Staging/Production Deployment، DB Migration یا صارف کے اصل Credentials تبدیل نہیں کیے گئے۔
- حالات: **Repository HEAD (main)** `224c39bcb8c28f77504c7348dbad41226753c7e8`; **Deployed Version:** نامعلوم؛ **Live DB Version:** نامعلوم؛ **Migration State:** نامعلوم؛ **Live Verification Status:** انجام نہیں پائی۔
- **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

## صاف فیصلہ
اس نشست نے ثابت شدہ Source Defects، Native Cross-File Bridges، Language UI اور Operator Reality Reporting کو بہتر کیا۔ اس کے باوجود حقیقی File00 Owner، Signed External Providers، مکمل Staging/Browser/Performance/Restore/Live Verification کے بغیر File02 کو "100 فیصد مکمل" یا "Production Ready" کہنا غلط ہوگا۔ PR #25 Draft رکھا جائے؛ PR #24 اور #23 کو الگ سے Merge نہ کریں۔ Backend Runtime Version `1.4.0`، DB Schema `1.4.0`، Passkey Schema `1.1.0` ابھی صرف امیدوار اعلانات ہیں۔
