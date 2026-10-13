<?php

namespace App\Support;

class CommandCenterErrors
{
    public const CATALOG = [
        'OK' => [
            'severity' => 'info',
            'message' => 'Operation completed.',
        ],
        'STATUS_PROXY_FAILED' => [
            'severity' => 'error',
            'message' => 'Laravel could not read command-center status.',
        ],
        'RADIO_STATUS_PROXY_FAILED' => [
            'severity' => 'error',
            'message' => 'Laravel could not read command-center radio status.',
        ],
        'RADIO_SCAN_PROXY_FAILED' => [
            'severity' => 'error',
            'message' => 'Laravel could not start command-center radio scan.',
        ],
        'RADIO_PREFLIGHT_PROXY_FAILED' => [
            'severity' => 'error',
            'message' => 'Laravel could not run command-center radio preflight.',
        ],
        'RADIO_AUTO_HOP_PROXY_FAILED' => [
            'severity' => 'error',
            'message' => 'Laravel could not run command-center auto-hop.',
        ],
        'RADIO_CHANNEL_PROXY_FAILED' => [
            'severity' => 'error',
            'message' => 'Laravel could not update the command-center radio channel.',
        ],
        'PREFLIGHT_RADIO_SCAN_REQUIRED' => [
            'severity' => 'error',
            'message' => 'A fresh command-center radio scan is required before takeoff.',
        ],
        'PREFLIGHT_RADIO_CONGESTED' => [
            'severity' => 'warning',
            'message' => 'The operating channel appears congested.',
        ],
        'RADIO_SCAN_UNAVAILABLE' => [
            'severity' => 'warning',
            'message' => 'No supported radio scanner is available on this command-center host.',
        ],
        'RADIO_SCAN_FAILED' => [
            'severity' => 'error',
            'message' => 'Command-center radio scan failed.',
        ],
        'INVALID_RADIO_CHANNEL' => [
            'severity' => 'error',
            'message' => 'Requested radio channel is not supported.',
        ],
        'HOP_UNSUPPORTED_HARDWARE' => [
            'severity' => 'warning',
            'message' => 'Automatic channel application requires router or radio hardware integration.',
        ],
        'OPENWRT_APPLY_NOT_CONFIGURED' => [
            'severity' => 'error',
            'message' => 'OpenWrt apply is enabled but router SSH settings are incomplete.',
        ],
        'OPENWRT_APPLY_FAILED' => [
            'severity' => 'error',
            'message' => 'OpenWrt router rejected or failed the channel apply command.',
        ],
        'OPENWRT_INVALID_RADIO' => [
            'severity' => 'error',
            'message' => 'OpenWrt radio section name is invalid.',
        ],
        'DRONE_NOT_FLYING' => [
            'severity' => 'warning',
            'message' => 'Flight command ignored because the drone is not flying.',
        ],
    ];

    public static function describe(?string $code): array
    {
        $code = $code ?: 'OK';
        $rootCode = explode(':', $code, 2)[0];
        $meta = self::CATALOG[$rootCode] ?? [
            'severity' => 'error',
            'message' => 'Uncataloged command-center error.',
        ];

        return [
            'code' => $rootCode,
            'severity' => $meta['severity'],
            'message' => $meta['message'],
            'raw' => $code,
        ];
    }

    public static function payload(string $code, ?string $message = null, array $extra = []): array
    {
        return array_merge([
            'ok' => false,
            'error' => $code,
            'error_info' => self::describe($code),
            'message' => $message ?? self::describe($code)['message'],
        ], $extra);
    }
}
