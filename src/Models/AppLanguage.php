<?php

namespace Aslnbxrz\SimpleTranslation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AppLanguage extends Model
{
    protected $table = 'app_languages';
    protected $fillable = ['name', 'code', 'icon', 'is_active'];
    public $timestamps = false;

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInActive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }
}
