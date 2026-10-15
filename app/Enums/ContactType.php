<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactType: string
{
    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
}
