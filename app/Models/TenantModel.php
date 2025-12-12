<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    /**
     * すべてのテナント用モデルは、この connection を使う
     */
    protected $connection = 'tenant';
}
