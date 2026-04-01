<?php

namespace App\Enums;

enum UserRole: string
{
    case Poster = 'poster';
    case Admin = 'admin';
}
