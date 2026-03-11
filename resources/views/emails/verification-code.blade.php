@component('mail::message')
# NaraBox Email Verification

**OPERATOR_IDENTIFICATION_REQUIRED**

Your verification code is:

<div style="text-align: center; margin: 40px 0;">
    <div style="display: inline-block; background: #121010; border: 3px solid #038C65; padding: 20px 40px; font-family: 'Oswald', sans-serif; font-size: 48px; font-weight: 900; letter-spacing: 8px; color: #038C65;">
        {{ $code }}
    </div>
</div>

**SECURITY_PROTOCOL:** This code will expire in 15 minutes. Do not share this code with anyone.

**MISSION_STATUS:** Enter this code in the verification screen to complete your registration.

---

<div style="text-align: center; margin-top: 40px; color: #666; font-size: 12px;">
    <p style="margin: 0;">If you did not request this code, please ignore this email.</p>
    <p style="margin: 10px 0 0 0;">© {{ date('Y') }} NaraBox. All rights reserved.</p>
</div>

@endcomponent
