<?php

namespace App\Enums;

enum HubOwnerRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
