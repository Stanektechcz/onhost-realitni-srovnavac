<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyReviewReport extends Model
{
    protected $fillable = ['reporter_id', 'reporter_email', 'reason', 'description'];

    public function review(): BelongsTo { return $this->belongsTo(AgencyReview::class, 'agency_review_id'); }
}
