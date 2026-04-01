<?php

namespace App\Enums;

enum DocsRetrievalMethod: string
{
    case UrlDirect = 'url_direct';
    case OAuth = 'oauth';
    case ServiceAccount = 'service_account';
}
