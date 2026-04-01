<?php

namespace App\Enums;

enum PostStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Draft = 'draft';
    case Approved = 'approved';
    case Published = 'published';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
