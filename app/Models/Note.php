<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['funeral_home_id', 'user_id', 'body'])]
class Note extends Model
{
    /**
     * @return BelongsTo<FuneralHome, $this>
     */
    public function funeralHome(): BelongsTo
    {
        return $this->belongsTo(FuneralHome::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
