<?php

namespace App\Http\Middleware;

use App\Services\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenant
{
    public function __construct(
        protected TenantResolver $tenantResolver
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        // ▼ プラットフォーム側UIから「どのテナントを見るか」を指定できるようにする
        //    例）X-Tenant-Subdomain: demo
        //        → demo.langit.local として解決させる
        $overrideSubdomain = $request->header('X-Tenant-Subdomain');

        if (! empty($overrideSubdomain)) {
            $baseDomain = config('app.tenant_base_domain'); // 例: langit.local

            // subdomain.baseDomain の形にして既存ロジックをそのまま使う
            $virtualHost = sprintf('%s.%s', $overrideSubdomain, $baseDomain);

            $this->tenantResolver->resolveFromHost($virtualHost);
        } else {
            // 従来通り Host ベースで解決
            $this->tenantResolver->resolveFromHost($host);
        }

        return $next($request);
    }
}
