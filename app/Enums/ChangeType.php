<?php

namespace App\Enums;

enum ChangeType: string
{
    case NewHome = 'new_home';
    case NewLicense = 'new_license';
    case LicenseStatusChanged = 'license_status_changed';
    case Closure = 'closure';
    case NameChanged = 'name_changed';
    case NewWebsite = 'new_website';
    case WebsiteChanged = 'website_changed';
    case NewPhone = 'new_phone';
    case PhoneChanged = 'phone_changed';
    case EmailChanged = 'email_changed';
    case AddressChanged = 'address_changed';
}
