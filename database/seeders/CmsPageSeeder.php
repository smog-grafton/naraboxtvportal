<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;

/**
 * Store-ready legal/help copy for NaraBox TV (VJ-narrated streaming, Uganda).
 * Edit anytime in Filament → CMS Pages.
 */
class CmsPageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'sort_order' => 10,
                'body' => $this->privacyBody(),
            ],
            [
                'slug' => 'terms-of-service',
                'title' => 'Terms of Service',
                'sort_order' => 20,
                'body' => $this->termsBody(),
            ],
            [
                'slug' => 'dmca',
                'title' => 'DMCA / Copyright',
                'sort_order' => 30,
                'body' => $this->dmcaBody(),
            ],
            [
                'slug' => 'help-center',
                'title' => 'Help Center',
                'sort_order' => 40,
                'body' => $this->helpBody(),
            ],
            [
                'slug' => 'data-deletion',
                'title' => 'Data deletion & account closure',
                'sort_order' => 50,
                'body' => $this->dataDeletionBody(),
            ],
        ];

        foreach ($pages as $row) {
            CmsPage::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'title' => $row['title'],
                    'body' => $row['body'],
                    'is_published' => true,
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }

    private function privacyBody(): string
    {
        return <<<'HTML'
<h2>Introduction</h2>
<p>NaraBox TV (“NaraBox”, “we”, “us”) operates a streaming service focused on <strong>VJ (video jockey) narrated</strong> movies and series, celebrating Uganda’s narration culture. This Privacy Policy explains what information we collect, how we use it, and your choices. By using our website or mobile apps, you agree to this policy.</p>

<h2>Who we are</h2>
<p>NaraBox TV provides access to licensed or authorised audiovisual content presented with professional VJ narration. <strong>VJs and media channels on our platform are added and verified by our team</strong>; we do not operate an open, unmoderated upload service for the public.</p>

<h2>Information we collect</h2>
<h3>Account & profile</h3>
<p>When you register, we may collect your name, email address, phone number (if you use phone sign-in), password hash, and optional profile details (such as a profile photo if you choose to upload one).</p>

<h3>Sign-in providers</h3>
<p>If you sign in with <strong>Google</strong> (or other providers we enable), we receive identifiers and basic profile information from that provider as permitted by your consent with them.</p>

<h3>Payments & subscriptions</h3>
<p>Payment processing may be handled by third-party gateways. We store transaction references and subscription status as needed to provide the service; card or wallet details are handled according to the payment provider’s policies.</p>
<p>To verify certain manual payments, you may upload a <strong>screenshot or image of a payment proof</strong> through the app. That image is used only for verification and support.</p>

<h3>Watch activity & preferences</h3>
<p>We may log titles you play, progress, favourites, and similar usage data to personalise recommendations, resume playback, and improve the product.</p>

<h3>Device & technical data</h3>
<p>We collect technical information such as device type, operating system version, app version, IP address, and diagnostic logs to secure the service, troubleshoot issues, and understand performance.</p>

<h3>Push notifications</h3>
<p>If you opt in, we register a <strong>push notification token</strong> with our systems to send you updates (e.g. new releases, account notices). You can disable notifications in your device settings.</p>

<h3>Advertising identifier</h3>
<p><strong>We do not use the advertising ID for personalised ads</strong> in the current NaraBox TV app build. If we introduce ad-supported features in the future, we will update this policy and, where required, obtain consent before using advertising identifiers.</p>

<h3>Mobile app permissions (as configured)</h3>
<p>Depending on your platform and the features you use, the app may request:</p>
<ul>
<li><strong>Camera</strong> — for capturing or updating a profile photo (iOS/Android usage strings describe this).</li>
<li><strong>Photo library / media / storage</strong> — for selecting images such as payment proof uploads.</li>
<li><strong>Notifications</strong> — for optional push messages.</li>
<li><strong>Network access</strong> — required for streaming, API calls, and sign-in.</li>
</ul>
<p>We only use these capabilities for the stated purposes. You can decline optional permissions, though some features may not work.</p>

<h3>Cookies & similar technologies (web)</h3>
<p>Our website may use cookies or local storage for session management, preferences, and analytics. You can control cookies through your browser.</p>

<h2>How we use information</h2>
<ul>
<li>Provide streaming, accounts, billing, and customer support</li>
<li>Verify VJ/creator identities and maintain catalogue quality</li>
<li>Detect fraud, abuse, and security incidents</li>
<li>Comply with law and respond to lawful requests</li>
<li>Send service-related communications (and marketing only where permitted)</li>
</ul>

<h2>Legal bases (where applicable)</h2>
<p>Where GDPR or similar laws apply, we rely on contract (providing the service), legitimate interests (security, analytics, product improvement), consent (e.g. marketing or optional features), and legal obligation.</p>

<h2>Sharing</h2>
<p>We may share data with hosting providers, payment processors, email/SMS providers, analytics tools, and professional advisers, under strict confidentiality. We do not sell your personal information.</p>

<h2>Retention</h2>
<p>We keep data as long as needed for the purposes above, including legal, tax, and dispute resolution. Some logs may be retained in shortened or aggregated form.</p>

<h2>Your rights</h2>
<p>Depending on your region, you may have rights to access, correct, delete, restrict, or port your data, and to object to certain processing. Contact us using the details in the app or on our website. You may also <strong>delete your account</strong> as described on our <strong>Data deletion</strong> page.</p>

<h2>Children</h2>
<p>The service is not directed at children under the age required by local law to consent without a parent. We do not knowingly collect personal information from young children. Parents who believe a child has provided data should contact us.</p>

<h2>International transfers</h2>
<p>We may process data in Uganda and other countries where we or our suppliers operate. We use appropriate safeguards where required.</p>

<h2>Changes</h2>
<p>We may update this Privacy Policy. Material changes will be posted on this page and, where appropriate, notified in-app or by email.</p>

<h2>Contact</h2>
<p>For privacy questions or requests, use the <strong>Contact</strong> option in the app or the contact form on our website, or the support email published there.</p>
HTML;
    }

    private function termsBody(): string
    {
        return <<<'HTML'
<h2>Agreement</h2>
<p>These Terms of Service (“Terms”) govern your use of NaraBox TV’s website, mobile applications, and related services (“Service”). By creating an account or using the Service, you agree to these Terms and to our Privacy Policy.</p>

<h2>The Service</h2>
<p>NaraBox TV offers streaming of movies, series, and related content, often with <strong>VJ narration</strong>—a cultural format popular in Uganda. Content is offered subject to licensing, distribution rights, and our editorial standards.</p>
<p><strong>VJs and partner channels are onboarded and verified by NaraBox.</strong> We aim to feature legitimate, authorised programming and to respond promptly to valid rights concerns.</p>

<h2>Eligibility & accounts</h2>
<p>You must provide accurate registration information and keep your credentials secure. You are responsible for activity under your account. We may suspend or terminate accounts that violate these Terms or harm the Service or other users.</p>

<h2>Subscriptions & payments</h2>
<p>Paid plans, rentals, or purchases are described at checkout. <strong>Fees, billing cycles, and renewal rules</strong> depend on the plan and payment method you select. Unless stated otherwise, subscriptions renew until you cancel according to the instructions we provide (e.g. via your app store or payment provider).</p>
<p>We may change prices or plans with reasonable notice where required by law. Taxes may apply.</p>
<p><strong>Refunds</strong> follow the policy shown at purchase time and applicable store rules. Digital content may be non-refundable once substantially consumed, except where law requires otherwise.</p>

<h2>Acceptable use</h2>
<p>You agree not to:</p>
<ul>
<li>Circumvent technical protections, geographic restrictions where enforced, or access controls</li>
<li>Copy, redistribute, or publicly perform our content outside the Service except as allowed</li>
<li>Use bots, scrapers, or excessive automation without permission</li>
<li>Harass others, upload malware, or interfere with the Service</li>
<li>Misrepresent your identity or affiliation</li>
</ul>

<h2>Content & maturity</h2>
<p>Titles may carry maturity guidance (e.g. family, teen, adult themes). <strong>We do not permit pornographic or illegal content.</strong> If you see material that violates our rules or your rights, contact us or use our DMCA process.</p>

<h2>Intellectual property</h2>
<p>The Service, branding, and content are protected by copyright and other laws. NaraBox and its licensors retain all rights not expressly granted to you. Your licence is personal, non-transferable, and limited to streaming within the Service as permitted by your plan.</p>

<h2>Third-party links</h2>
<p>We may link to third-party sites or integrations. We are not responsible for their content or practices.</p>

<h2>Disclaimer & limitation of liability</h2>
<p>The Service is provided “as available.” Streaming quality may vary with your device and network. To the fullest extent permitted by law, we disclaim implied warranties and limit liability for indirect or consequential damages. Our total liability for claims relating to the Service is capped at the amount you paid us in the twelve months before the claim (or a nominal amount if none), except where law forbids such limits.</p>

<h2>Indemnity</h2>
<p>You agree to indemnify and hold harmless NaraBox and its team against claims arising from your misuse of the Service or violation of these Terms, where permitted by law.</p>

<h2>Governing law</h2>
<p>These Terms are governed by the laws applicable in Uganda, without regard to conflict-of-law rules, unless mandatory consumer protections in your country say otherwise.</p>

<h2>Changes</h2>
<p>We may modify these Terms. Continued use after the effective date constitutes acceptance of the updated Terms where allowed by law.</p>

<h2>Contact</h2>
<p>Questions about these Terms: use in-app <strong>Contact</strong> or our website contact channels.</p>
HTML;
    }

    private function dmcaBody(): string
    {
        return <<<'HTML'
<h2>Copyright respect</h2>
<p>NaraBox TV respects intellectual property rights. We respond to notices of alleged infringement that comply with the <strong>Digital Millennium Copyright Act (DMCA)</strong> where applicable, and with other applicable copyright laws.</p>

<h2>Our model</h2>
<p>We work with <strong>verified VJs and rights holders</strong> and aim to distribute content only where we have appropriate authorisation. If you believe material on NaraBox infringes your copyright, please send a complete notice as described below.</p>

<h2>Filing a notice</h2>
<p>Your notice should include substantially the following:</p>
<ol>
<li>Identification of the copyrighted work claimed to have been infringed (or a representative list if multiple works).</li>
<li>Identification of the material that is claimed to be infringing <strong>with enough detail</strong> for us to locate it (e.g. title, URL or deep link within our app/web, episode name, approximate date).</li>
<li>Your contact information: name, address, telephone number, and email.</li>
<li>A statement that you have a good faith belief that use of the material is not authorised by the copyright owner, its agent, or the law.</li>
<li>A statement that the information in the notification is accurate, and under penalty of perjury, that you are authorised to act on behalf of the owner of an exclusive right that is allegedly infringed.</li>
<li>A physical or electronic signature of the copyright owner or a person authorised to act on their behalf.</li>
</ol>
<p>Incomplete notices may delay processing.</p>

<h2>Counter-notification</h2>
<p>If content was removed and you believe the removal was mistaken, you may submit a counter-notification including your contact details, identification of the removed material, a statement under penalty of perjury that you believe the removal was a mistake, consent to jurisdiction where appropriate, and your signature. We may restore content in line with applicable law.</p>

<h2>Repeat infringers</h2>
<p>We may terminate accounts of users who are repeat infringers in appropriate circumstances.</p>

<h2>Where to send notices</h2>
<p>Send DMCA and copyright notices to the <strong>designated contact email</strong> published on our website (Contact / Legal). We may update this address from time to time.</p>
<p><em>Do not send general support requests to the DMCA inbox; use the standard contact channels for playback or billing help.</em></p>
HTML;
    }

    private function helpBody(): string
    {
        return <<<'HTML'
<h2>Welcome to NaraBox TV Help</h2>
<p>Quick answers for common questions. For account-specific issues, use <strong>Contact us</strong> in the app.</p>

<h2>What is a VJ?</h2>
<p>A <strong>VJ (video jockey)</strong> narrates and contextualises films and shows—often in local languages—so audiences can enjoy stories in a familiar voice. NaraBox highlights verified VJs and curated channels.</p>

<h2>Signing in</h2>
<p>You can register with email or use supported social sign-in (e.g. Google). If you forget your password, use <strong>Forgot password</strong> on the login screen.</p>

<h2>Subscriptions & payments</h2>
<p>Choose a plan from the app or website. Payment methods depend on your region and active gateways. Keep proof of payment when using manual methods; you may be asked to upload a screenshot for verification.</p>

<h2>Playback issues</h2>
<ul>
<li>Check your internet connection and try lowering quality if buffering persists.</li>
<li>Update the app to the latest version.</li>
<li>Restart the app or device.</li>
</ul>

<h2>Privacy & data</h2>
<p>Read our <strong>Privacy Policy</strong> for details on data we collect. To delete your account and associated personal data, see <strong>Data deletion & account closure</strong>.</p>

<h2>Content concerns</h2>
<p>For copyright or serious content issues, see our <strong>DMCA</strong> page and follow the notice instructions.</p>
HTML;
    }

    private function dataDeletionBody(): string
    {
        return <<<'HTML'
<h2>Your control over data</h2>
<p>NaraBox TV provides clear ways to <strong>delete your account and personal data</strong>, in line with Google Play and Apple App Store expectations for user transparency.</p>

<h2>Delete account in the app</h2>
<p>If you are logged in, open <strong>Profile → Settings</strong> (or equivalent) and use the <strong>Delete account</strong> option if available. This starts permanent removal of your profile and associated personal data, subject to legal retention below.</p>

<h2>API deletion (advanced)</h2>
<p>Authenticated clients may call <code>DELETE /api/v1/auth/account</code> with a valid bearer token to request account deletion. This endpoint is intended for the official NaraBox TV app and documented integrations.</p>

<h2>Email / support request</h2>
<p>If you cannot access the app, contact us via the <strong>Contact</strong> form or support email listed on our website. Include the email address on your account and a brief request to delete your data. We may ask for identity verification to protect your account.</p>

<h2>What we delete</h2>
<p>We remove or anonymise personal identifiers, profile data, preferences, and similar records tied to your account. Some information may be retained in <strong>aggregated or de-identified</strong> form for analytics, or as required for legal, tax, fraud-prevention, or dispute resolution (e.g. invoice records for a limited period).</p>

<h2>Timing</h2>
<p>We process verified deletion requests without undue delay, typically within a few business days, unless law requires otherwise or a dispute is ongoing.</p>

<h2>After deletion</h2>
<p>You may register again with a new account; previous purchase history may not be restored.</p>
HTML;
    }
}
