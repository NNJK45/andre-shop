<?php

namespace App\Domain\User\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Admin = 'admin';
    case Supplier = 'supplier';
}
