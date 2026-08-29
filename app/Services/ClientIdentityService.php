<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class ClientIdentityService
{
    public function ip(Request $request): ?string
    {
        $remote = $request->server('REMOTE_ADDR');
        $trusted = config('security.trusted_proxies', []);
        $fromTrustedProxy = is_string($remote) && $remote !== '' && $trusted !== []
            && IpUtils::checkIp($remote, $trusted);

        if ($fromTrustedProxy) {
            $cloudflareIp = trim((string) $request->header('CF-Connecting-IP'));
            if (filter_var($cloudflareIp, FILTER_VALIDATE_IP)) {
                return $cloudflareIp;
            }
        }

        $resolved = $request->ip();

        return is_string($resolved) && filter_var($resolved, FILTER_VALIDATE_IP) ? $resolved : null;
    }

    public function deviceId(Request $request): ?string
    {
        $header = (string) config('security.device_header', 'X-NBX-Device-ID');
        $value = trim((string) $request->header($header));
        $max = (int) config('security.device_id_max_length', 128);

        if ($value === '' || strlen($value) > $max || ! preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $value)) {
            return null;
        }

        return mb_strtolower($value);
    }
}
