# فائل 02: ماسٹر پلان v12 کی چوتھی جامع، مزید تحقیقی و اصلاحی نشست — اصل شواہد و باقی امور
**تاریخ:** 10 نومبر 2026  
**Source of truth:** صارف کی `Sabri-Website-Master-Plan-v12.docx`، حصہ F02، پیراگراف 935–983  
**محفوظ کام:** Draft PR #26، `audit/f02-v12-session3-20261110-recovery-dpop`، PR #25 کے بعد؛ PR #25، PR #24 کے بعد؛ PR #24، PR #23 کے بعد؛ `main` پر کوئی تبدیلی نہیں۔  
**عملی ماحول:** صرف GitHub Candidate + Disposable WordPress/MariaDB، حقیقی Hostinger Deployed Artifact/DB سامنے نہیں آیا۔

## 1. تمہیدِ حاکم — اصل فیصلہ
48 تقاضے (12 FR، 10 NFR، 2 CEN، 24 X24) اپنی جگہ برقرار ہیں۔ 48 source rows موجود ہونے یا GitHub CI سبز ہونے سے **48 مستقل، حقیقی End-to-End Production Acceptances ثابت نہیں ہوتیں**۔ File00 شناخت، عمر/سرپرست، اختیار اور Collision/Recovery کا canonical owner؛ File02 اصل Authentication factor ceremony اور assurance کا owner ہے۔ ہر Deployment کی Live Reality Freeze اور Parity Audit الگ ہوگا۔ اس نشست کے Source Defects کو Live Bugs نہ سمجھا جائے۔

## 2. سات الگ ثابت شدہ Candidate Source نقائص، اصلاح اور ثبوت
| شمار | ماسٹر پلان | اصل ثابت شدہ نقص | Fail-first / Corrective evidence | Status |
|---|---|---|---|---|
| S3-01 | F02-X24-019 / NFR-003 | رجسٹرڈ breached-password Provider کا `null` جواب Local-only fallback بن کر غلط منظوری دے سکتا تھا | PHP 7.4/8.3 RED Run #42795660821؛ `has_filter` readiness + `sauth_password_breach_lookup_unavailable`؛ source checks | Candidate corrected؛ Vendor runtime acceptance pending |
| S3-02 | F02-X24-011 / NFR-003 | ناقابلِ فہم `apply_after` کی `strtotime(false)` coercion، cooling-off سے پہلے Owner کا عمل ممکن بناتی تھی | اسی RED Run؛ Strict `Y-m-d H:i:s` UTC calendar/date validation، future/invalid deny، elapsed success mock | Candidate corrected؛ real File00 owner pending |
| S3-03 | F02-X24-012 / NFR-003 | Collision Owner کی کارروائی کامیاب، آخری status update ناکام ہونے پر پرانا پیغام "Retry safely" دیتا تھا، حالاں کہ Side Effect غیر یقینی تھا | RED Run؛ `sauth_collision_reconciliation_required`، processing retained، no re-invocation | Candidate corrected؛ supervised File00 operations pending |
| S3-04 | F02-X24-020 | DPoP proof `htu` میں query/fragment/userinfo یا غلط `alg` / JWK type Cryptographic Verifier تک جاسکتا تھا | RED Run؛ signature verification سے پہلے strict structural reject؛ previous signed-before-replay safety preserved | Candidate corrected؛ real JOSE verifier pending |
| S3-05 | F02-X24-007 / NFR-002 | Timeline detail VALUE میں `reason=person@example.test` جیسے PII یا bearer-like tokens محفوظ ہوسکتے تھے، جبکہ صرف keys پر regex موجود تھی | PHP 7.4/8.3 RED Run #42797809530 (nonzero-exit truthful CI)؛ bounded typed allowlist | Candidate corrected for **new** events؛ existing deployed records unknown |
| S3-06 | F02-X24-007 / NFR-008 | Security Timeline DB read failure پر empty list بن سکتی تھی، بجائے explicit degraded error کے | RED Run #42797809530؛ typed `sauth_timeline_unavailable` + UI alert | Candidate corrected؛ real DB fault injection staging pending |
| S3-07 | F02-X24-008 / X24-009 | "This Was Not Me" میں compromise-watch persistence ناکام ہو مگر other-sessions revoke + signal کامیاب ہو تو false success ممکن تھا | Exact RED Run #42799438958؛ read-back of seven-day watch، emergency containment attempt and typed denial | Candidate corrected؛ real incident replay/ops pending |

**تعداد میں احتیاط:** یہ **7 منفرد، Source-level defect families** ہیں؛ Test Steps، fail-first re-runs یا PR commits کو الگ Production bugs نہ شمار کیا جائے۔ Zero live incidents resolved/verified.

## 3. آزمائش کی اصل ساکھ: False-Green Code Quality Bug
PHP میں `exit("FAIL message")` پیغام چھاپ کر **exit code 0** دے سکتا ہے؛ اس بنا پر سابقہ بعض سبز GitHub ٹیسٹ مثبت ثبوت نہ تھے۔ اس نشست میں نئی اور سابقہ QA فائلوں سے 42 اس نوعیت کے string-exit expressions کو محفوظ `fwrite(STDERR, message); exit(1)` صورت میں تبدیل کیا گیا۔ Nonzero استعمال ہونے پر FedCM Source Test کا قدیم غلط Relative Path خود ظاہر ہوا؛ `dirname(__DIR__,2)` درست کیا گیا۔ مزید 20 Security/Consumer test files کے لیے دائمی Static Guard `v12-ci-exitcode-audit.php`، اور عین audit-branch push + PHP 7.4/8.3 جامع tests workflow شامل ہے۔

- یہ اصلاح **QA System** میں ہے، Live auth code defect نہیں۔
- پرانے سبز نتائج جو صرف `exit(string)` والے ٹیسٹ پر منحصر تھے، اب ازخود کافی ثبوت نہیں۔
- درست ٹیسٹوں کی نئی کامیاب GitHub Run کو عین HEAD سے ملا کر دیکھا جائے؛ کسی سابق SHA کا Pass نئے SHA کی ضمانت نہیں۔

## 4. لسانی اور بصری اضافے
Security Center، `This Was Not Me`، `Secure My Account` اور Collision Resolution Wizard کے **12 مزید مستعمل UI Strings** کو WordPress Urdu/Arabic catalogs میں شامل کرکے محفوظ/escape شدہ ترجمہ پذیر متن اور explicit RTL shell direction دی گئی۔ Source-level fail-first PHP7.4/8.3 RED Run #42798912242 پھر اصلاح کی گئی۔ پہلے سے موجود Login/Signup، Google، Reset، Access، Timeline کے تراجم برقرار ہیں۔

**Real PHP translation acceptance:** Disposable WordPress **7.0.1**، File00 1.2.44 کے ساتھ اصل `.l10n.php` Urdu/Arabic catalogs کی Load+Translation جانچ GitHub Run #42798767499 کے native locale step میں کامیاب رہی۔ یہ Browser/Screen Reader/Full Locale acceptance نہیں۔

## 5. اصلاح کا مسئلہ، محدود کردہ اثر، اور باقی 13 عملی Workstreams
- **File00:** Collision owner callback/identity proof اور Recovery approval/outcome reconciliation کا حقیقی نفاذ Source Tests سے الگ؛ کوئی فرضی Canonical Owner نہیں بنایا گیا۔
- **Signed External Provider:** CAEP, RISC, FedCM, JOSE DPoP, FIDO Metadata، SMTP/Google/OIDC اور واقعی Breach Range Provider کی approved credentials/certificates/issuer/nonce/error/migration checks باقی۔
- **File24:** Passkey/current-subject/risk projection candidate prior PR #25 میں source/consumer CI verified؛ real staging role/assurance acceptance باقی۔
- **File25:** scoped semantic design-token bridge prior PR #25 میں موجود؛ keyboard/screenreader/contrast/zoom/RTL/Forced Colors/Performance p75/p95 real acceptance باقی۔
- **File26:** private authentication has deliberate no-index/no-store policy؛ current deployed index review، stale index/erasure acceptance باقی۔
- **Operations:** actual retry/deadletter/reconciliation, legal hold, historical security timeline detail audit, provider outage drill، backup/restore/rollback and Founder-approved deploy remain open.
- **Localization:** مزید security UI اب ترجمہ پذیر، PHP catalog WP7 real load verified؛ مکمل متن، Timezone، Browser RTL، Accessibility/P75/P95 قبولیت ابھی باقی۔
- **QA:** exact-HEAD 20-fixture static exit-code guard + failed-first regressions؛ branch final SHA CI دوبارہ ضروری۔

ان 13 زمروں کو **13 confirmed code bugs** یا **13 live incidents** نہ لکھا جائے۔

## 6. لازمی Five-Reality Snapshot
| خانہ | حالت |
|---|---|
| **Repository main HEAD** | `224c39bcb8c28f77504c7348dbad41226753c7e8` |
| **Candidate Source** | PR #26 stacked, candidate plugin `1.4.0`, candidate DB `1.4.0`, passkey `1.1.0` |
| **Deployed Version / exact file checksum** | **نامعلوم، غیر مصدقہ** |
| **Live DB Version / tables / options** | **نامعلوم، غیر مصدقہ** |
| **Migration State** | **نامعلوم، غیر مصدقہ** |
| **Live Verification Status** | **نہیں ہوئی** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

## 7. فیصلہ و اگلا حقیقی مرحلہ
PR #26 Draft اور غیر ضم رہے۔ Parent PR #25/#24/#23 بھی خودکار Merge/Deploy سے محفوظ رہیں۔ Exact compiled package checksum + CI کے بعد حقیقی File00 Owner, approved external providers, browser/RTL/a11y/latency, Hostinger Staging Fresh/Upgrade/Restore/Rollback، اور Owner/Founder approval شرط ہیں۔ Live symptom کی صورت میں **Live Freeze → Active Deployed Code & DB/Migration → Repository Parity → Root Cause → Fix/Test/Package/Deploy → Live Re-test/Parity** کی ترتیب بدلے بغیر کام ہوگا۔
