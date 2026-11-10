# صابری پلیٹ فارم — اکاؤنٹ کے لازمی حفاظتی ضوابط (۱۰ نومبر ۲۰۲۶)

یہ File 02 کی repository سطح پر نئی سہولت ہے، لائیو نفاذ کا ثبوت نہیں۔

**موجودہ بنیادی لازمی کنٹرول:** File 02 کے password-based signup/reset کے لیے ۱۲ حروف، signed email verification، خطرناک password-login پر passkey challenge، rate limits، server-side session revocation اور WebAuthn verification۔ File 00 identity/roles/MFA policy کا واحد مالک ہے۔

**نئی تبدیلی:** دونوں registration طریقوں (password/Google) پر سات حفاظتی قواعد پڑھنے اور ان کی پابندی کی لازمی checkbox؛ سرور اسے account creation سے پہلے چیک کرتا ہے۔ اس کی تصدیق کبھی enrolled MFA یا محفوظ account ہونے کی ضمانت نہیں۔ /account/safety/ کا عمومی no-store/noindex صفحہ ہر نئے رکن کو رجسٹریشن سے پہلے مکمل اردو قواعد پڑھنے دیتا ہے؛ passkeys/sessions/File 00 security actions صرف authenticated اکاؤنٹ میں ظاہر ہوتے ہیں۔

**لازمی مستقبل نفاذی دروازہ:** عمومی صارفین پر جبری passkeys یا privileged roles پر mandatory MFA کی نئی policy صرف File 00 کے منظور شدہ live-compatible contract، واقعی provider، tested recovery and alternative methods، accessibility، staging rollout اور incident rollback کے بعد enforcement ہوگی۔ غیر معاون browser یا گم شدہ device سے مستقل account lockout نہیں ہوگا۔ Host WAF، DDoS، backup اور domain MFA الگ operational controls ہیں، File 02 کے اندر ان کا جھوٹا نفاذ نہیں ہوگا۔

**QA:** `php tests/account-safety-guidance.php`، مکمل PHP lint/CI، File 00/02 integration، security session regression، RTL/keyboard/mobile، recovery, WebAuthn and provider outage، staging then live re-test۔ ہر رپورٹ میں GitHub HEAD / deployed version / DB version / migration state / live verification الگ درج ہوں۔


## ۱۰ نومبر کی نظرِ ثانی
- Safety Rules لنک اب checkbox label کے اندر نہیں؛ keyboard اور screen-reader رسائی واضح، غلطی سے checkbox tick ہونے کا خطرہ کم۔
- ہر رجسٹریشن میں موجودہ policy version بھی سرور پر جانچا جاتا ہے؛ پرانا یا غائب version قبول نہیں۔
- Account creation کی کامیابی کے بعد File 00 audit پر مختصر versioned acknowledgment receipt کی کوشش ہوتی ہے؛ موجودہ audit API کی ناکامی میں یہ مستقل ثبوت کی ضمانت نہیں۔ مستقبل میں مستند mandatory consent ledger File 00 کا ذمہ ہوگا۔
