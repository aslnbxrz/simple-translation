<?php

namespace Aslnbxrz\SimpleTranslation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppTextTranslation extends Model
{
    protected $table = 'app_text_translations';
    protected $fillable = ['lang_code', 'app_text_id', 'text'];
    public $timestamps = false;

    public function text(): BelongsTo
    {
        return $this->belongsTo(AppText::class, 'app_text_id');
    }
}