<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Tenant::updateOrCreate(
            ['subdomain' => 'demo'],
            [
                'name'        => 'デモ高校',
                'db_host'     => 'mysql',          // Sail の MySQL サービス名
                'db_port'     => 3306,
                'db_database' => 'demo_school_db', // これから作るDB名
                'db_username' => 'sail',
                'db_password' => 'password',
            ]
        );
    }
}
