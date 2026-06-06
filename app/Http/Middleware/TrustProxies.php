<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Railway (and most PaaS providers) sit behind a load balancer that
     * terminates TLS and forwards traffic over plain HTTP internally.
     * Without this, request()->secure() returns false, HTTPS redirects
     * loop, and session cookies marked "secure" never get set.
     *
     * '*' trusts all proxies — safe on Railway since the internal network
     * is not reachable from the public internet. If you add a custom domain
     * via Cloudflare, set TRUST_PROXIES=cloudflare in Railway env vars and
     * adjust the $proxies property accordingly.
     */
    protected $proxies = '*';

    /**
     * Forward headers Railway sends so Laravel can reconstruct the original
     * HTTPS request URL, client IP, and port correctly.
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
