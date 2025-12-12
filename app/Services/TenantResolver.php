<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantResolver
{
    /**
     * ホスト名からテナントを判定し、DB接続を切り替える
     */
    public function resolveFromHost(string $host): ?Tenant
    {
        $baseDomain = config('app.tenant_base_domain');

        // ベースドメイン（例: langit.local）や localhost の場合は何もしない
        if ($host === $baseDomain || $host === 'localhost') {
            return null;
        }

        $subdomain = $this->extractSubdomain($host, $baseDomain);

        if (! $subdomain) {
            return null;
        }

        $tenant = Tenant::where('subdomain', $subdomain)->first();

        if (! $tenant) {
            throw new NotFoundHttpException('Tenant not found.');
        }

        $this->switchConnection($tenant);

        return $tenant;
    }

    /**
     * ホスト名からサブドメイン部分を取り出す
     * 例: demo.langit.local + langit.local → demo
     */
    protected function extractSubdomain(string $host, string $baseDomain): ?string
    {
        // host が baseDomainと同じ → サブドメイン無し
        if ($host === $baseDomain) {
            return null;
        }

        // host 末尾に baseDomain が付いている場合、その前をサブドメインとみなす
        if (str_ends_with($host, '.'.$baseDomain)) {
            return str_replace('.'.$baseDomain, '', $host);
        }

        // それ以外（例えば単に 127.0.0.1 とか）は一旦サブドメイン無し扱い
        return null;
    }

    /**
     * tenant 接続のDB設定を書き換え、再接続する
     */
    protected function switchConnection(Tenant $tenant): void
    {
        $config = Config::get('database.connections.tenant');

        $config['host']     = $tenant->db_host;
        $config['port']     = $tenant->db_port;
        $config['database'] = $tenant->db_database;
        $config['username'] = $tenant->db_username;
        $config['password'] = $tenant->db_password;

        Config::set('database.connections.tenant', $config);

        DB::purge('tenant');
        DB::reconnect('tenant');
    }
}
