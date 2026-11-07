<?php

namespace App\Http\Controllers;

use App\Models\SavedSearch;

class SavedSearchUnsubscribeController extends Controller
{
    public function __invoke(SavedSearch $search)
    {
        $search->forceFill(['notify' => false])->save();

        return response()->view('account.unsubscribed', ['search' => $search]);
    }
}
