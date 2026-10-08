<?php

namespace App\Support;

/**
 * Works out the address phones should use to reach this PC, so QR codes keep working
 * when the Wi-Fi router hands the PC a new IP address.
 */
class Network
{
    /** This PC's current private network (Wi-Fi/LAN) IPv4 address, or null if offline. */
    public static function lanIp(): ?string
    {
        // A UDP "connect" sends no packets; it just asks Windows which local address it would use.
        $socket = @stream_socket_client('udp://8.8.8.8:53', $errno, $error, 1);

        if ($socket) {
            $local = stream_socket_get_name($socket, false);
            fclose($socket);
            $ip = $local ? explode(':', $local)[0] : null;

            if (self::isPrivate($ip)) {
                return $ip;
            }
        }

        foreach (gethostbynamel(gethostname()) ?: [] as $ip) {
            if (self::isPrivate($ip)) {
                return $ip;
            }
        }

        return null;
    }

    public static function isPrivate(?string $ip): bool
    {
        return $ip !== null
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false
            && ! str_starts_with($ip, '127.');
    }

    public static function isLoopback(?string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true);
    }

    /**
     * Base address used inside QR codes.
     * - Real deployments (APP_URL is a domain or public address): APP_URL as configured.
     * - Local/classroom setups (APP_URL is localhost or a private IP): this PC's current Wi-Fi address,
     *   on the port the app is being used on.
     */
    public static function qrBaseUrl(): string
    {
        $configured = rtrim((string) config('app.url'), '/');
        $host = parse_url($configured, PHP_URL_HOST);

        if (! self::isLoopback($host) && ! self::isPrivate($host)) {
            return $configured;
        }

        $ip = self::lanIp();

        if (! $ip) {
            return $configured;
        }

        $port = app()->runningInConsole()
            ? (parse_url($configured, PHP_URL_PORT) ?: 8000)
            : request()->getPort();

        return 'http://'.$ip.(in_array((int) $port, [80, 0], true) ? '' : ':'.$port);
    }
}
