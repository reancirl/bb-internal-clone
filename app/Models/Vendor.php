<?php

namespace App\Models;

use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    public const TYPE_STORE = 'store';

    public const TYPE_SUPPLIER = 'supplier';

    public const TYPES = [self::TYPE_STORE, self::TYPE_SUPPLIER];

    protected $fillable = [
        'name',
        'type',
        'location',
        'phone',
        'email',
        'url',
        'notes',
    ];
}
