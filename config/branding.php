<?php

/*
|--------------------------------------------------------------------------
| Company branding — defaults for a new installation
|--------------------------------------------------------------------------
|
| One installation serves one company. What the company sets under
| Settings → Company Branding is kept in the database (app_settings
| "branding") and its logos in storage/app/branding, so upgrades never
| touch them. These are only the starting values for a fresh install;
| install.sh fills the company name in through `php artisan app:branding`.
|
| Colours left empty (null) keep the product's own design colours.
|
*/

return [

    'defaults' => [
        'name' => env('COMPANY_NAME', 'OLT Panel'),
        'legal_name' => env('COMPANY_LEGAL_NAME'),         // empty = the name
        'app_title' => env('COMPANY_APP_TITLE'),           // empty = "<name> ERP"
        'tagline' => 'Network Operations & Business Panel',

        'email' => env('COMPANY_EMAIL'),
        'accounts_email' => null,                          // empty = email
        'phone' => env('COMPANY_PHONE'),
        'website' => env('COMPANY_WEBSITE'),
        'address' => env('COMPANY_ADDRESS'),               // one line per printed line
        'address_short' => null,                           // empty = last address line

        'invoice_prefix' => 'INV/',

        'copyright_holder' => null,                        // empty = the legal name
        'footer_credit' => null,                           // e.g. "Developed by *Jane*, IT" (*bold*)
        'privacy_url' => null,
        'terms_url' => null,

        'primary_color' => null,                           // buttons, links, highlights
        'secondary_color' => null,                         // sidebar and sign-in panel
        'accent_color' => null,                            // current menu item, sign-in accents
        'background_color' => null,                        // page background

        // Uploaded files, relative to storage/app/branding (set from the settings page).
        'logo' => null,                                    // wide logo
        'icon' => null,                                    // square mark
        'favicon' => null,
    ],

    // Uploads: types and largest size (KB).
    'upload_mimes' => ['logo' => 'png,jpg,jpeg,webp', 'icon' => 'png,jpg,jpeg,webp', 'favicon' => 'png,ico'],
    'upload_max_kb' => 2048,

];
