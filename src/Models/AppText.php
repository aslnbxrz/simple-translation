<?php

namespace Aslnbxrz\SimpleTranslation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppText extends Model
{
    protected $table = 'app_texts';
    protected $fillable = ['scope', 'text'];

    public $timestamps = false;

    public function translations(): HasMany
    {
        return $this->hasMany(AppTextTranslation::class);
    }

    public function translate(string $langCode, string $translation): void
    {
        $this->translations()->updateOrCreate(['lang_code' => $langCode], [
            'text' => $translation
        ]);
    }
}
