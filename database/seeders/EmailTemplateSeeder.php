<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            EmailTemplate::updateOrCreate(
                ['name' => $template['name']],
                $template
            );
        }

        $this->command?->info('Narabox email templates seeded successfully.');
    }

    private function templates(): array
    {
        return [
            $this->template(
                name: 'verification_code',
                subject: 'Verify your Narabox TV account',
                preheader: 'Use this one-time code to finish signing in to Narabox TV.',
                type: 'transactional',
                body: $this->frame(
                    eyebrow: 'Account verification',
                    headline: 'Complete your sign in',
                    body: '<p>Your Narabox TV verification code is below.</p>
<div style="margin:32px 0;padding:18px 20px;border:2px solid #0F8B6D;background:#050505;font-size:36px;letter-spacing:8px;font-weight:800;text-align:center;color:#F3C969;">{{code}}</div>
<p>This code expires in 15 minutes. If you did not request it, you can ignore this email.</p>'
                ),
                variables: ['code' => '6-digit verification code']
            ),
            $this->template(
                name: 'welcome',
                subject: 'Welcome to Narabox TV, {{user_name}}',
                preheader: 'Your account is ready, with free titles and current plan pricing inside.',
                type: 'transactional',
                body: $this->frame(
                    eyebrow: 'Welcome aboard',
                    headline: 'Your Narabox TV access is live',
                    body: '<p>Hello <strong>{{user_name}}</strong>,</p>
<p>Welcome to Narabox TV. Your account is ready and you can start watching immediately.</p>
<p>Start here with three free picks:</p>
<ul style="padding-left:18px;margin:16px 0;">
<li><a href="{{watch_url}}" style="color:#F3C969;text-decoration:none;">Free Movie 1</a></li>
<li><a href="{{watch_url}}" style="color:#F3C969;text-decoration:none;">Free Movie 2</a></li>
<li><a href="{{watch_url}}" style="color:#F3C969;text-decoration:none;">Free Movie 3</a></li>
</ul>
<p>Current plans, rent options, and buy options are now available in your account dashboard.</p>',
                    ctaLabel: 'Open Narabox TV',
                    ctaUrl: '{{watch_url}}'
                ),
                variables: [
                    'user_name' => 'Recipient display name',
                    'watch_url' => 'Landing page or dashboard URL',
                ]
            ),
            $this->template(
                name: 'payment_success',
                subject: 'Payment confirmed on Narabox TV',
                preheader: 'Your payment was successful and your access is ready.',
                type: 'transactional',
                body: $this->transactionFrame('Payment successful', '<p>Your payment has been confirmed.</p>
<p><strong>Amount:</strong> UGX {{amount}}<br><strong>Status:</strong> {{status}}<br><strong>Date:</strong> {{created_at}}</p>'),
                variables: ['amount' => 'Transaction amount', 'status' => 'Payment status', 'created_at' => 'Transaction date']
            ),
            $this->template(
                name: 'subscription_success',
                subject: 'Your Narabox TV subscription is active',
                preheader: 'Your subscription has been activated successfully.',
                type: 'transactional',
                body: $this->transactionFrame('Subscription activated', '<p>Your <strong>{{subscription_plan}}</strong> subscription is now active.</p>
<p><strong>Amount paid:</strong> UGX {{amount}}<br><strong>Expiry:</strong> {{expiry_date}}</p>', '{{watch_url}}', 'Start watching'),
                variables: ['subscription_plan' => 'Plan name', 'amount' => 'Amount paid', 'expiry_date' => 'Plan expiry date', 'watch_url' => 'Watch URL']
            ),
            $this->template(
                name: 'rent_success',
                subject: 'Rental confirmed: {{movie_title}}',
                preheader: 'Your rental was successful and the title is ready to watch.',
                type: 'transactional',
                body: $this->transactionFrame('Rental confirmed', '<p>You have successfully rented <strong>{{movie_title}}</strong>.</p>
<p><strong>Amount paid:</strong> UGX {{amount}}<br><strong>Access until:</strong> {{expiry_date}}</p>', '{{watch_url}}', 'Watch now'),
                variables: ['movie_title' => 'Movie title', 'amount' => 'Amount paid', 'expiry_date' => 'Rental expiry', 'watch_url' => 'Watch URL']
            ),
            $this->template(
                name: 'buy_success',
                subject: 'Purchase confirmed: {{movie_title}}',
                preheader: 'Your purchase was successful and has been added to your library.',
                type: 'transactional',
                body: $this->transactionFrame('Purchase confirmed', '<p>You now own <strong>{{movie_title}}</strong> on Narabox TV.</p>
<p><strong>Amount paid:</strong> UGX {{amount}}</p>', '{{watch_url}}', 'Open your library'),
                variables: ['movie_title' => 'Movie title', 'amount' => 'Amount paid', 'watch_url' => 'Watch URL']
            ),
            $this->template(
                name: 'payment_failed',
                subject: 'Payment failed on Narabox TV',
                preheader: 'Your payment could not be completed.',
                type: 'transactional',
                body: $this->transactionFrame('Payment failed', '<p>We could not complete your transaction.</p>
<p><strong>Status:</strong> {{status}}</p>
<p>Please try again or contact support if the issue continues.</p>'),
                variables: ['status' => 'Failed status text']
            ),
            $this->template(
                name: 'payment_pending',
                subject: 'Your payment is still pending',
                preheader: 'We are still waiting for payment confirmation.',
                type: 'transactional',
                body: $this->transactionFrame('Payment pending', '<p>Your payment is still being processed.</p>
<p><strong>Status:</strong> {{status}}</p>')
            ),
            $this->template(
                name: 'payment_cancelled',
                subject: 'Payment cancelled on Narabox TV',
                preheader: 'Your payment was cancelled before completion.',
                type: 'transactional',
                body: $this->transactionFrame('Payment cancelled', '<p>Your transaction was cancelled before completion.</p>
<p><strong>Status:</strong> {{status}}</p>')
            ),
            $this->template(
                name: 'password_reset',
                subject: 'Reset your Narabox TV password',
                preheader: 'Use the secure link below to set a new password.',
                type: 'transactional',
                body: $this->frame(
                    eyebrow: 'Password reset',
                    headline: 'Create a new password',
                    body: '<p>A password reset was requested for <strong>{{email}}</strong>.</p>
<p>Use the secure link below to continue.</p>',
                    ctaLabel: 'Reset password',
                    ctaUrl: '{{reset_url}}'
                ),
                variables: ['email' => 'Recipient email', 'reset_url' => 'Password reset URL']
            ),
            $this->template(
                name: 'new_movie_added',
                subject: 'Now on Narabox TV: {{movie_title}}',
                preheader: 'A fresh title has landed on Narabox TV.',
                type: 'promotional',
                body: $this->promoFrame('New movie added', '<p><strong>{{movie_title}}</strong> is now available on Narabox TV.</p>
<p>Open it now and start watching.</p>', '{{watch_url}}', 'Watch this movie')
            ),
            $this->template(
                name: 'new_show_added',
                subject: 'Now streaming: {{show_title}}',
                preheader: 'A new series is now available on Narabox TV.',
                type: 'promotional',
                body: $this->promoFrame('New TV show added', '<p><strong>{{show_title}}</strong> is now available on Narabox TV.</p>', '{{watch_url}}', 'Open the show')
            ),
            $this->template(
                name: 'new_episode_added',
                subject: 'New episode available: {{episode_title}}',
                preheader: 'A new episode just dropped on Narabox TV.',
                type: 'promotional',
                body: $this->promoFrame('New episode added', '<p><strong>{{episode_title}}</strong> is now ready to watch.</p>', '{{watch_url}}', 'Watch episode')
            ),
            $this->template(
                name: 'top_trending_movies',
                subject: 'Top trending movies on Narabox TV',
                preheader: 'The movies people are watching most right now.',
                type: 'promotional',
                body: $this->promoFrame('Top trending movies', '<p>Here are the titles pulling the biggest audience on Narabox TV right now.</p>', '{{watch_url}}', 'See trending movies')
            ),
            $this->template(
                name: 'latest_movies',
                subject: 'Latest movies on Narabox TV',
                preheader: 'Fresh additions are live now.',
                type: 'promotional',
                body: $this->promoFrame('Latest movies', '<p>New releases and recent additions are waiting for you.</p>', '{{watch_url}}', 'Browse latest movies')
            ),
            $this->template(
                name: 'selected_tv_shows',
                subject: 'TV shows selected for you',
                preheader: 'A handpicked list from Narabox TV.',
                type: 'promotional',
                body: $this->promoFrame('Selected for you', '<p>We pulled together a TV lineup you should not miss.</p>', '{{watch_url}}', 'Explore shows')
            ),
            $this->template(
                name: 'movie_of_the_day',
                subject: 'Movie of the day on Narabox TV',
                preheader: 'One title worth watching today.',
                type: 'promotional',
                body: $this->promoFrame('Movie of the day', '<p>Tonight’s recommended watch is waiting for you.</p>', '{{watch_url}}', 'Open movie')
            ),
            $this->template(
                name: 'weekend_watchlist',
                subject: 'Your Narabox TV weekend watchlist',
                preheader: 'Line up your next weekend sessions.',
                type: 'promotional',
                body: $this->promoFrame('Weekend watchlist', '<p>We pulled together a weekend-ready selection for your next session.</p>', '{{watch_url}}', 'View watchlist')
            ),
            $this->template(
                name: 'continue_watching_reminder',
                subject: 'Pick up where you left off',
                preheader: 'Your next session is waiting on Narabox TV.',
                type: 'promotional',
                body: $this->promoFrame('Continue watching', '<p>You still have something worth finishing on Narabox TV.</p>', '{{watch_url}}', 'Continue watching')
            ),
            $this->template(
                name: 'subscription_renewal_reminder',
                subject: 'Your Narabox TV plan expires soon',
                preheader: 'Renew now to keep watching without interruption.',
                type: 'promotional',
                body: $this->promoFrame('Subscription reminder', '<p>Your <strong>{{subscription_plan}}</strong> plan expires on <strong>{{expiry_date}}</strong>.</p>', '{{watch_url}}', 'Renew now')
            ),
            $this->template(
                name: 'expired_subscription_win_back',
                subject: 'Come back to Narabox TV',
                preheader: 'Your subscription expired, but the next watch is ready when you are.',
                type: 'promotional',
                body: $this->promoFrame('We saved your seat', '<p>Your access expired, but Narabox TV still has new titles waiting for you.</p>', '{{watch_url}}', 'See current plans')
            ),
            $this->template(
                name: 'comment_reply',
                subject: 'Someone replied to your Narabox TV comment',
                preheader: 'A new reply just came in.',
                type: 'transactional',
                body: $this->transactionFrame('New comment reply', '<p>Someone replied to your comment on <strong>{{movie_title}}</strong>.</p>
<p>Sign in to read and continue the conversation.</p>', '{{watch_url}}', 'Open comments')
            ),
            $this->template(
                name: 'admin_activity_alert',
                subject: 'Narabox TV admin alert: {{title}}',
                preheader: 'A new backend activity alert needs your attention.',
                type: 'transactional',
                body: $this->frame(
                    eyebrow: 'Admin alert',
                    headline: '{{title}}',
                    body: '<p>{{message}}</p><p><strong>Time:</strong> {{created_at}}</p>'
                )
            ),
        ];
    }

    private function template(string $name, string $subject, string $preheader, string $type, string $body, array $variables = []): array
    {
        return [
            'name' => $name,
            'subject' => $subject,
            'preheader' => $preheader,
            'preview_text' => $preheader,
            'template_type' => $type,
            'body' => $body,
            'variables' => $variables,
            'is_active' => true,
        ];
    }

    private function transactionFrame(string $headline, string $body, ?string $ctaUrl = null, string $ctaLabel = 'Open Narabox TV'): string
    {
        return $this->frame(
            eyebrow: 'Transaction update',
            headline: $headline,
            body: $body,
            ctaLabel: $ctaUrl ? $ctaLabel : null,
            ctaUrl: $ctaUrl,
        );
    }

    private function promoFrame(string $headline, string $body, ?string $ctaUrl = null, string $ctaLabel = 'Open Narabox TV'): string
    {
        return $this->frame(
            eyebrow: 'Narabox TV',
            headline: $headline,
            body: $body . '<p style="margin-top:28px;font-size:12px;color:#8E8E8E;">To stop promotional updates, use this link: <a href="{{unsubscribe_url}}" style="color:#8E8E8E;">unsubscribe</a></p>',
            ctaLabel: $ctaLabel,
            ctaUrl: $ctaUrl,
        );
    }

    private function frame(string $eyebrow, string $headline, string $body, ?string $ctaLabel = null, ?string $ctaUrl = null): string
    {
        $cta = '';

        if ($ctaLabel && $ctaUrl) {
            $cta = '<div style="margin-top:32px;"><a href="' . $ctaUrl . '" style="display:inline-block;padding:14px 24px;background:#D7B56D;color:#050505;text-decoration:none;font-weight:700;letter-spacing:.04em;text-transform:uppercase;border:1px solid #D7B56D;">' . e($ctaLabel) . '</a></div>';
        }

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Narabox TV</title>
</head>
<body style="margin:0;padding:0;background:#020202;color:#F5F5F5;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . e($headline) . '</div>
    <div style="padding:32px 18px;background:#020202;">
        <div style="max-width:640px;margin:0 auto;border:1px solid #1D1D1D;background:#0A0A0A;">
            <div style="padding:18px 24px;border-bottom:1px solid #1D1D1D;background:#050505;">
                <div style="font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:#D7B56D;font-weight:700;">' . e($eyebrow) . '</div>
                <div style="margin-top:10px;font-size:32px;line-height:1.05;font-weight:800;color:#FFFFFF;text-transform:uppercase;">' . e($headline) . '</div>
            </div>
            <div style="padding:28px 24px 32px 24px;font-size:15px;line-height:1.7;color:#D7D7D7;">
                ' . $body . '
                ' . $cta . '
            </div>
            <div style="padding:18px 24px;border-top:1px solid #1D1D1D;background:#050505;font-size:12px;color:#777777;line-height:1.6;">
                Narabox TV<br>
                Streaming movies, shows, and VJ content without the noise.
            </div>
        </div>
    </div>
</body>
</html>';
    }
}
