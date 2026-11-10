<?php
defined( 'ABSPATH' ) || exit;
/**
 * Authentication-owned guidance only. No duplicate MFA/identity authority.
 * File 00 governs MFA requirements; File 24 independently verifies assurance.
 */
final class SAUTH_Account_Safety {
    const POLICY_VERSION = '2026-11-10';

    public static function registration_acknowledged( array $input ) {
        return isset( $input['accept_security_rules'] )
            && is_scalar( $input['accept_security_rules'] )
            && hash_equals( '1', (string) $input['accept_security_rules'] );
    }

    public static function render() {
        $rules = array(
            'منفرد اور مضبوط پاس ورڈ رکھیں؛ دستیاب ہو تو پاس ورڈ مینیجر استعمال کریں۔',
            'پاس ورڈ، OTP، ریکوری کوڈ، تصدیقی لنک یا پاس کی کی نجی کلید کسی کو نہ دیں۔',
            'اپنی ای میل کی ملکیت کی لازمی تصدیق کریں اور بحالی کا محفوظ طریقہ برقرار رکھیں۔',
            'اصل ڈومین جانچیں، جعلی پیغامات اور خطرناک فائلوں سے بچیں۔',
            'آلے اور براؤزر کو اپ ڈیٹ رکھیں؛ مشترک آلے سے لاگ آؤٹ کریں۔',
            'مریض کی رپورٹس، تصاویر اور کیس ہسٹری صرف مجاز نجی راستے سے بھیجیں۔',
            'مشتبہ رسائی پر فعال سیشن منسوخ کریں، محفوظ آلے سے اسناد بدلیں اور رپورٹ کریں۔',
        );
        ob_start(); ?>
        <section class="sa-auth-shell" dir="rtl" lang="ur" aria-labelledby="sauth-safety-title">
            <div class="sa-auth-card sa-auth-wide">
                <h1 id="sauth-safety-title"><?php echo esc_html( 'اکاؤنٹ کی لازمی حفاظتی ہدایات' ); ?></h1>
                <p><?php echo esc_html( 'یہ قواعد تمام صارفین کے لیے ہیں؛ محض ہدایات پڑھنا MFA کے فعال ہونے کا ثبوت یا ہر حملے سے تحفظ کی ضمانت نہیں۔' ); ?></p>
                <ol><?php foreach ( $rules as $rule ) : ?><li><?php echo esc_html( $rule ); ?></li><?php endforeach; ?></ol>
                <?php if ( is_user_logged_in() ) : ?>
                    <h2><?php echo esc_html( 'اپنی حفاظت کے عملی اختیارات' ); ?></h2>
                    <ul>
                        <li><a href="<?php echo esc_url( SAUTH_Passkeys::manager_url() ); ?>"><?php echo esc_html( 'پاس کی یا حفاظتی کلید کا انتظام' ); ?></a></li>
                        <li><a href="<?php echo esc_url( SA_Security::page_url( 'sessions' ) ); ?>"><?php echo esc_html( 'فعال سیشن دیکھیں یا منسوخ کریں' ); ?></a></li>
                        <li><a href="<?php echo esc_url( SA_Membership_Adapter::security_url() ); ?>"><?php echo esc_html( 'رکنیت کی حفاظتی ترتیبات' ); ?></a></li>
                    </ul>
                <?php else : ?>
                    <p><?php echo esc_html( 'ہر نیا رکن یہ قواعد رجسٹریشن سے پہلے پڑھ سکتا ہے؛ ذاتی سیشن، پاس کیز اور حفاظتی ترتیبات صرف اپنے اکاؤنٹ میں لاگ اِن کرنے کے بعد دستیاب ہیں۔' ); ?></p>
                    <a class="sa-secondary-button" href="<?php echo esc_url( SA_Membership_Adapter::login_url( SA_Security::page_url( 'safety' ) ) ); ?>"><?php echo esc_html( 'اپنے حفاظتی اختیارات کے لیے لاگ اِن کریں' ); ?></a>
                <?php endif; ?>
                <p class="sa-data-note"><?php echo esc_html( 'حساس اختیارات پر MFA یا تازہ تصدیق کی شرط File 00 کے مجاز اصول، File 02 کے آزمودہ تصدیقی نظام اور اصل نفاذی ثبوت سے طے ہوگی۔' ); ?></p>
            </div>
        </section>
        <?php return (string) ob_get_clean();
    }
}
