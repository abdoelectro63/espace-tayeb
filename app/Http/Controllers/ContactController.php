<?php

namespace App\Http\Controllers;

use App\Models\ContactSetting;
use Illuminate\Contracts\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        $contact = ContactSetting::settings();

        return view('store.contact', [
            'contact' => $contact,
            'metaTitle' => filled($contact->seo_title)
                ? $contact->seo_title
                : $contact->page_title,
            'metaDescription' => $contact->seo_description,
        ]);
    }
}
