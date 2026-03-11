@component('mail::message')
# Welcome to NaraBox, {{ $user->name }}!

**REGISTRATION_SUCCESSFUL**

Your account has been successfully created and verified. You now have access to the NaraBox streaming platform.

---

## Your Account Details

- **Name:** {{ $user->name }}
- **Email:** {{ $user->email }}
- **Plan:** {{ $user->plan }}
- **Status:** Active

---

## What's Next?

You can now:
- Browse our extensive library of movies and TV shows
- Watch live streams
- Rent or purchase content
- Subscribe to premium plans
- Access your personal dashboard

<div style="text-align: center; margin: 40px 0;">
    <a href="{{ config('app.frontend_url', 'http://localhost:3000') }}/dashboard" style="display: inline-block; background: #038C65; color: #FFFFFF; padding: 15px 40px; text-decoration: none; font-family: 'Oswald', sans-serif; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; border: 3px solid #038C65;">
        Access Dashboard
    </a>
</div>

---

<div style="text-align: center; margin-top: 40px; color: #666; font-size: 12px;">
    <p style="margin: 0;">Thank you for joining NaraBox!</p>
    <p style="margin: 10px 0 0 0;">© {{ date('Y') }} NaraBox. All rights reserved.</p>
</div>

@endcomponent
